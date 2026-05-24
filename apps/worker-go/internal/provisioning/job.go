package provisioning

type Payload struct {
	Action  string         `json:"action"`
	Product ProductPayload `json:"product"`
}

type ProductPayload struct {
	Code string `json:"code"`
	Type string `json:"type"`
}

type Job struct {
	ID          string
	Type        string
	OrderID     string
	ServiceID   string
	UserID      int64
	Attempts    int
	ProductCode string
	ProductType string
	Action      string
	Payload     Payload
}

type Result struct {
	Status     string
	ExternalID string
	Config     map[string]any
}
