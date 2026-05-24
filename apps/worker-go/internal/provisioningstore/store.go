package provisioningstore

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"

	"github.com/Chinsusu/billing-v3/apps/worker-go/internal/provisioning"
)

type Job = provisioning.Job
type Result = provisioning.Result

type Store struct {
	db *sql.DB
}

func NewStore(db *sql.DB) *Store {
	return &Store{db: db}
}

func (s *Store) ClaimNext(ctx context.Context) (Job, bool, error) {
	tx, err := s.db.BeginTx(ctx, nil)
	if err != nil {
		return Job{}, false, err
	}

	var (
		job          Job
		payloadBytes []byte
	)

	row := tx.QueryRowContext(ctx, `select id, order_id, service_id, user_id, type, payload from provisioning_jobs where status = 'pending' and (available_at is null or available_at <= now()) order by created_at asc limit 1 for update skip locked`)
	if err := row.Scan(&job.ID, &job.OrderID, &job.ServiceID, &job.UserID, &job.Type, &payloadBytes); err != nil {
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

	if _, err := tx.ExecContext(ctx, `update provisioning_jobs set status = 'failed', last_error = $1, processed_at = now(), updated_at = now() where id = $2`, cause.Error(), job.ID); err != nil {
		_ = tx.Rollback()

		return err
	}

	return tx.Commit()
}
