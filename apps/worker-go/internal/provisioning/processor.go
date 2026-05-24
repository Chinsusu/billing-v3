package provisioning

import (
	"errors"
	"fmt"
)

type Processor struct{}

func (Processor) Process(job Job) (Result, error) {
	if job.ID == "" || job.OrderID == "" || job.ServiceID == "" {
		return Result{}, errors.New("job id, order id, and service id are required")
	}
	if job.Type != "provision_service" {
		return Result{}, fmt.Errorf("unsupported job type %q", job.Type)
	}
	if job.Action != "provision" {
		return Result{}, fmt.Errorf("unsupported action %q", job.Action)
	}
	if job.ProductType != "proxy" && job.ProductType != "vps" {
		return Result{}, fmt.Errorf("unsupported product type %q", job.ProductType)
	}

	return Result{
		Status:     "processed",
		ExternalID: fmt.Sprintf("sandbox-%s-%s", job.ProductType, job.ServiceID),
	}, nil
}
