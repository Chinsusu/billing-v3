package provisioning

import "testing"

func TestProcessorRejectsInvalidJob(t *testing.T) {
	processor := Processor{}

	_, err := processor.Process(Job{
		ID:          "job-1",
		Type:        "provision_service",
		OrderID:     "",
		ServiceID:   "service-1",
		ProductType: "proxy",
		Action:      "provision",
	})

	if err == nil {
		t.Fatal("expected invalid job error")
	}
}

func TestProcessorProcessesProvisionJob(t *testing.T) {
	processor := Processor{}

	result, err := processor.Process(Job{
		ID:          "job-1",
		Type:        "provision_service",
		OrderID:     "order-1",
		ServiceID:   "service-1",
		ProductCode: "proxy-vn-30d",
		ProductType: "proxy",
		Action:      "provision",
	})

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if result.Status != "processed" {
		t.Fatalf("unexpected status %q", result.Status)
	}
	if result.ExternalID != "sandbox-proxy-service-1" {
		t.Fatalf("unexpected external id %q", result.ExternalID)
	}
}
