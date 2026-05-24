package provisioningstore

import (
	"context"
	"database/sql"
	"database/sql/driver"
	"encoding/json"
	"errors"
	"reflect"
	"regexp"
	"testing"
	"time"

	"github.com/DATA-DOG/go-sqlmock"
)

func TestClaimNextClaimsPendingJob(t *testing.T) {
	db, mock, closeDB := newMockDB(t)
	defer closeDB()

	store := NewStore(db)
	rows := sqlmock.NewRows([]string{"id", "order_id", "service_id", "user_id", "type", "attempts", "payload"}).
		AddRow("job-1", "order-1", "service-1", int64(7), "provision_service", 0, []byte(`{"action":"provision","product":{"code":"proxy-vn-30d","type":"proxy"}}`))

	mock.ExpectBegin()
	mock.ExpectQuery(regexp.QuoteMeta(`select id, order_id, service_id, user_id, type, attempts, payload from provisioning_jobs where status = 'pending' and (available_at is null or available_at <= now()) order by created_at asc limit 1 for update skip locked`)).
		WillReturnRows(rows)
	mock.ExpectExec(regexp.QuoteMeta(`update provisioning_jobs set status = 'processing', attempts = attempts + 1, updated_at = now() where id = $1`)).
		WithArgs("job-1").
		WillReturnResult(sqlmock.NewResult(0, 1))
	mock.ExpectCommit()

	job, ok, err := store.ClaimNext(context.Background())

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if !ok {
		t.Fatal("expected job to be claimed")
	}
	if job.ID != "job-1" || job.ServiceID != "service-1" || job.Payload.Product.Code != "proxy-vn-30d" {
		t.Fatalf("unexpected job: %#v", job)
	}
	if job.Attempts != 1 {
		t.Fatalf("expected claimed attempt count 1, got %d", job.Attempts)
	}
	if err := mock.ExpectationsWereMet(); err != nil {
		t.Fatal(err)
	}
}

func TestClaimNextReturnsFalseWhenNoPendingJob(t *testing.T) {
	db, mock, closeDB := newMockDB(t)
	defer closeDB()

	store := NewStore(db)
	mock.ExpectBegin()
	mock.ExpectQuery(regexp.QuoteMeta(`select id, order_id, service_id, user_id, type, attempts, payload from provisioning_jobs where status = 'pending' and (available_at is null or available_at <= now()) order by created_at asc limit 1 for update skip locked`)).
		WillReturnError(sql.ErrNoRows)
	mock.ExpectRollback()

	_, ok, err := store.ClaimNext(context.Background())

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if ok {
		t.Fatal("expected no claimed job")
	}
	if err := mock.ExpectationsWereMet(); err != nil {
		t.Fatal(err)
	}
}

func TestMarkProcessedActivatesServiceAndMarksJobProcessed(t *testing.T) {
	db, mock, closeDB := newMockDB(t)
	defer closeDB()

	store := NewStore(db)
	mock.ExpectBegin()
	mock.ExpectExec(regexp.QuoteMeta(`update services set status = 'active', external_id = $1, config = $2, provisioned_at = now(), updated_at = now() where id = $3`)).
		WithArgs("sandbox-proxy-service-1", jsonObjectArg{want: map[string]any{"ip": "203.0.113.10", "region": "sgp1"}}, "service-1").
		WillReturnResult(sqlmock.NewResult(0, 1))
	mock.ExpectExec(regexp.QuoteMeta(`update provisioning_jobs set status = 'processed', processed_at = now(), last_error = null, updated_at = now() where id = $1`)).
		WithArgs("job-1").
		WillReturnResult(sqlmock.NewResult(0, 1))
	mock.ExpectCommit()

	err := store.MarkProcessed(context.Background(), Job{ID: "job-1", ServiceID: "service-1"}, Result{
		ExternalID: "sandbox-proxy-service-1",
		Config:     map[string]any{"ip": "203.0.113.10", "region": "sgp1"},
	})

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if err := mock.ExpectationsWereMet(); err != nil {
		t.Fatal(err)
	}
}

func TestMarkProcessedDefaultsNilConfigToEmptyObject(t *testing.T) {
	db, mock, closeDB := newMockDB(t)
	defer closeDB()

	store := NewStore(db)
	mock.ExpectBegin()
	mock.ExpectExec(regexp.QuoteMeta(`update services set status = 'active', external_id = $1, config = $2, provisioned_at = now(), updated_at = now() where id = $3`)).
		WithArgs("sandbox-proxy-service-1", jsonObjectArg{want: map[string]any{}}, "service-1").
		WillReturnResult(sqlmock.NewResult(0, 1))
	mock.ExpectExec(regexp.QuoteMeta(`update provisioning_jobs set status = 'processed', processed_at = now(), last_error = null, updated_at = now() where id = $1`)).
		WithArgs("job-1").
		WillReturnResult(sqlmock.NewResult(0, 1))
	mock.ExpectCommit()

	err := store.MarkProcessed(context.Background(), Job{ID: "job-1", ServiceID: "service-1"}, Result{ExternalID: "sandbox-proxy-service-1"})

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if err := mock.ExpectationsWereMet(); err != nil {
		t.Fatal(err)
	}
}

func TestMarkFailedRequeuesJobBeforeMaxAttempts(t *testing.T) {
	db, mock, closeDB := newMockDB(t)
	defer closeDB()

	store := NewStoreWithPolicy(db, RetryPolicy{MaxAttempts: 3, RetryBackoff: 45 * time.Second})
	mock.ExpectBegin()
	mock.ExpectExec(regexp.QuoteMeta(`update provisioning_jobs set status = 'pending', available_at = now() + ($1 * interval '1 second'), last_error = $2, processed_at = null, updated_at = now() where id = $3`)).
		WithArgs(int64(45), "provider timeout", "job-1").
		WillReturnResult(sqlmock.NewResult(0, 1))
	mock.ExpectCommit()

	err := store.MarkFailed(context.Background(), Job{ID: "job-1", Attempts: 2}, errors.New("provider timeout"))

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if err := mock.ExpectationsWereMet(); err != nil {
		t.Fatal(err)
	}
}

func TestMarkFailedPermanentlyFailsAtMaxAttempts(t *testing.T) {
	db, mock, closeDB := newMockDB(t)
	defer closeDB()

	store := NewStoreWithPolicy(db, RetryPolicy{MaxAttempts: 3, RetryBackoff: 45 * time.Second})
	mock.ExpectBegin()
	mock.ExpectExec(regexp.QuoteMeta(`update provisioning_jobs set status = 'failed', last_error = $1, processed_at = now(), updated_at = now() where id = $2`)).
		WithArgs("provider timeout", "job-1").
		WillReturnResult(sqlmock.NewResult(0, 1))
	mock.ExpectCommit()

	err := store.MarkFailed(context.Background(), Job{ID: "job-1", Attempts: 3}, errors.New("provider timeout"))

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if err := mock.ExpectationsWereMet(); err != nil {
		t.Fatal(err)
	}
}

func TestRecoverStuckRequeuesAndFailsOldProcessingJobs(t *testing.T) {
	db, mock, closeDB := newMockDB(t)
	defer closeDB()

	store := NewStoreWithPolicy(db, RetryPolicy{MaxAttempts: 3, RetryBackoff: 45 * time.Second})
	mock.ExpectBegin()
	mock.ExpectExec(regexp.QuoteMeta(`update provisioning_jobs set status = 'pending', available_at = now(), last_error = $1, updated_at = now() where status = 'processing' and updated_at <= now() - ($2 * interval '1 second') and attempts < $3`)).
		WithArgs("recovered stuck processing job", int64(300), 3).
		WillReturnResult(sqlmock.NewResult(0, 2))
	mock.ExpectExec(regexp.QuoteMeta(`update provisioning_jobs set status = 'failed', processed_at = now(), last_error = $1, updated_at = now() where status = 'processing' and updated_at <= now() - ($2 * interval '1 second') and attempts >= $3`)).
		WithArgs("recovered stuck processing job after max attempts", int64(300), 3).
		WillReturnResult(sqlmock.NewResult(0, 1))
	mock.ExpectCommit()

	result, err := store.RecoverStuck(context.Background(), 5*time.Minute)

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if result.Requeued != 2 || result.Failed != 1 {
		t.Fatalf("unexpected recovery result: %#v", result)
	}
	if err := mock.ExpectationsWereMet(); err != nil {
		t.Fatal(err)
	}
}

func TestMarkFailedStoresLastError(t *testing.T) {
	db, mock, closeDB := newMockDB(t)
	defer closeDB()

	store := NewStore(db)
	mock.ExpectBegin()
	mock.ExpectExec(regexp.QuoteMeta(`update provisioning_jobs set status = 'failed', last_error = $1, processed_at = now(), updated_at = now() where id = $2`)).
		WithArgs("provider rejected request", "job-1").
		WillReturnResult(sqlmock.NewResult(0, 1))
	mock.ExpectCommit()

	err := store.MarkFailed(context.Background(), Job{ID: "job-1"}, errors.New("provider rejected request"))

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if err := mock.ExpectationsWereMet(); err != nil {
		t.Fatal(err)
	}
}

func newMockDB(t *testing.T) (*sql.DB, sqlmock.Sqlmock, func()) {
	t.Helper()

	db, mock, err := sqlmock.New()
	if err != nil {
		t.Fatalf("failed to create sqlmock: %v", err)
	}

	return db, mock, func() { _ = db.Close() }
}

type jsonObjectArg struct {
	want map[string]any
}

func (arg jsonObjectArg) Match(value driver.Value) bool {
	var bytes []byte
	switch typed := value.(type) {
	case []byte:
		bytes = typed
	case string:
		bytes = []byte(typed)
	default:
		return false
	}

	var got map[string]any
	if err := json.Unmarshal(bytes, &got); err != nil {
		return false
	}

	return reflect.DeepEqual(got, arg.want)
}
