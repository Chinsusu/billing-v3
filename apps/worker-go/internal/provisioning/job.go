package provisioning

type Job struct {
	ID          string
	Type        string
	OrderID     string
	ServiceID   string
	ProductCode string
	ProductType string
	Action      string
}

type Result struct {
	Status     string
	ExternalID string
}
