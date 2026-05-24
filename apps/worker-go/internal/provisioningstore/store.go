package provisioningstore

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"time"

	"github.com/Chinsusu/billing-v3/apps/worker-go/internal/provisioning"
)

type Job = provisioning.Job
type Result = provisioning.Result

const (
	defaultMaxAttempts  = 3
	defaultRetryBackoff = time.Minute
	defaultStuckAfter   = 5 * time.Minute
)

type RetryPolicy struct {
	MaxAttempts  int
	RetryBackoff time.Duration
}

type RecoveryResult struct {
	Requeued int64
	Failed   int64
}

type Store struct {
	db     *sql.DB
	policy RetryPolicy
}

func NewStore(db *sql.DB) *Store {
	return NewStoreWithPolicy(db, RetryPolicy{MaxAttempts: 1, RetryBackoff: defaultRetryBackoff})
}

func NewStoreWithPolicy(db *sql.DB, policy RetryPolicy) *Store {
	return &Store{db: db, policy: normalizePolicy(policy)}
}

func (s *Store) ClaimNext(ctx context.Context) (Job, bool, error) {
	tx, err := s.db.BeginTx(ctx, nil)
	if err != nil {
		return Job{}, false, err
	}

	var (
		job          Job
		attempts     int
		payloadBytes []byte
	)

	row := tx.QueryRowContext(ctx, `select id, order_id, service_id, user_id, type, attempts, payload from provisioning_jobs where status = 'pending' and (available_at is null or available_at <= now()) order by created_at asc limit 1 for update skip locked`)
	if err := row.Scan(&job.ID, &job.OrderID, &job.ServiceID, &job.UserID, &job.Type, &attempts, &payloadBytes); err != nil {
		_ = tx.Rollback()
		if errors.Is(err, sql.ErrNoRows) {
			return Job{}, false, nil
		}

		return Job{}, false, err
	}

	if err := json.Unmarshal(payloadBytes, &job.Payload); err != nil {
		_ = tx.Rollback()

		return Job{}, false, err
	}
	job.Action = job.Payload.Action
	job.ProductCode = job.Payload.Product.Code
	job.ProductType = job.Payload.Product.Type
	job.Attempts = attempts + 1

	if _, err := tx.ExecContext(ctx, `update provisioning_jobs set status = 'processing', attempts = attempts + 1, updated_at = now() where id = $1`, job.ID); err != nil {
		_ = tx.Rollback()

		return Job{}, false, err
	}

	if err := tx.Commit(); err != nil {
		return Job{}, false, err
	}

	return job, true, nil
}

func (s *Store) MarkProcessed(ctx context.Context, job Job, result Result) error {
	tx, err := s.db.BeginTx(ctx, nil)
	if err != nil {
		return err
	}

	if _, err := tx.ExecContext(ctx, `update services set status = 'active', external_id = $1, provisioned_at = now(), updated_at = now() where id = $2`, result.ExternalID, job.ServiceID); err != nil {
		_ = tx.Rollback()

		return err
	}

	if _, err := tx.ExecContext(ctx, `update provisioning_jobs set status = 'processed', processed_at = now(), last_error = null, updated_at = now() where id = $1`, job.ID); err != nil {
		_ = tx.Rollback()

		return err
	}

	return tx.Commit()
}

func (s *Store) MarkFailed(ctx context.Context, job Job, cause error) error {
	tx, err := s.db.BeginTx(ctx, nil)
	if err != nil {
		return err
	}
	if cause == nil {
		cause = errors.New("unknown provisioning error")
	}

	if effectiveAttempts(job) >= s.policy.MaxAttempts {
		if _, err := tx.ExecContext(ctx, `update provisioning_jobs set status = 'failed', last_error = $1, processed_at = now(), updated_at = now() where id = $2`, cause.Error(), job.ID); err != nil {
			_ = tx.Rollback()

			return err
		}

		return tx.Commit()
	}

	if _, err := tx.ExecContext(ctx, `update provisioning_jobs set status = 'pending', available_at = now() + ($1 * interval '1 second'), last_error = $2, processed_at = null, updated_at = now() where id = $3`, durationSeconds(s.policy.RetryBackoff), cause.Error(), job.ID); err != nil {
		_ = tx.Rollback()

		return err
	}

	return tx.Commit()
}

func (s *Store) RecoverStuck(ctx context.Context, stuckAfter time.Duration) (RecoveryResult, error) {
	if stuckAfter <= 0 {
		stuckAfter = defaultStuckAfter
	}

	tx, err := s.db.BeginTx(ctx, nil)
	if err != nil {
		return RecoveryResult{}, err
	}

	requeueResult, err := tx.ExecContext(ctx, `update provisioning_jobs set status = 'pending', available_at = now(), last_error = $1, updated_at = now() where status = 'processing' and updated_at <= now() - ($2 * interval '1 second') and attempts < $3`, "recovered stuck processing job", durationSeconds(stuckAfter), s.policy.MaxAttempts)
	if err != nil {
		_ = tx.Rollback()

		return RecoveryResult{}, err
	}

	failResult, err := tx.ExecContext(ctx, `update provisioning_jobs set status = 'failed', processed_at = now(), last_error = $1, updated_at = now() where status = 'processing' and updated_at <= now() - ($2 * interval '1 second') and attempts >= $3`, "recovered stuck processing job after max attempts", durationSeconds(stuckAfter), s.policy.MaxAttempts)
	if err != nil {
		_ = tx.Rollback()

		return RecoveryResult{}, err
	}

	requeued, err := requeueResult.RowsAffected()
	if err != nil {
		_ = tx.Rollback()

		return RecoveryResult{}, err
	}
	failed, err := failResult.RowsAffected()
	if err != nil {
		_ = tx.Rollback()

		return RecoveryResult{}, err
	}

	if err := tx.Commit(); err != nil {
		return RecoveryResult{}, err
	}

	return RecoveryResult{Requeued: requeued, Failed: failed}, nil
}

func normalizePolicy(policy RetryPolicy) RetryPolicy {
	if policy.MaxAttempts <= 0 {
		policy.MaxAttempts = defaultMaxAttempts
	}
	if policy.RetryBackoff <= 0 {
		policy.RetryBackoff = defaultRetryBackoff
	}

	return policy
}

func effectiveAttempts(job Job) int {
	if job.Attempts <= 0 {
		return 1
	}

	return job.Attempts
}

func durationSeconds(duration time.Duration) int64 {
	if duration <= 0 {
		return 1
	}

	return int64((duration + time.Second - 1) / time.Second)
}
