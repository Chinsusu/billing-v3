package provisioning

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

type InternalExecutorProcessor struct {
	baseURL string
	token   string
	client  *http.Client
}

func NewInternalExecutorProcessor(baseURL string, token string, timeout time.Duration) InternalExecutorProcessor {
	if timeout <= 0 {
		timeout = 30 * time.Second
	}

	return NewInternalExecutorProcessorWithClient(baseURL, token, &http.Client{Timeout: timeout})
}

func NewInternalExecutorProcessorWithClient(baseURL string, token string, client *http.Client) InternalExecutorProcessor {
	if client == nil {
		client = &http.Client{Timeout: 30 * time.Second}
	}

	return InternalExecutorProcessor{
		baseURL: strings.TrimRight(baseURL, "/"),
		token:   token,
		client:  client,
	}
}

func (p InternalExecutorProcessor) Process(ctx context.Context, job Job) (Result, error) {
	if job.ID == "" {
		return Result{}, errors.New("job id is required")
	}
	if p.baseURL == "" {
		return Result{}, errors.New("backend internal url is required")
	}

	request, err := http.NewRequestWithContext(ctx, http.MethodPost, p.baseURL+"/internal/provisioning/jobs/"+job.ID+"/execute", bytes.NewReader([]byte("{}")))
	if err != nil {
		return Result{}, err
	}
	request.Header.Set("Content-Type", "application/json")
	request.Header.Set("Accept", "application/json")
	if p.token != "" {
		request.Header.Set("Authorization", "Bearer "+p.token)
	}

	response, err := p.client.Do(request)
	if err != nil {
		return Result{}, fmt.Errorf("call provisioning executor: %w", err)
	}
	defer response.Body.Close()

	body, err := io.ReadAll(response.Body)
	if err != nil {
		return Result{}, fmt.Errorf("read executor response: %w", err)
	}
	if response.StatusCode < http.StatusOK || response.StatusCode >= http.StatusMultipleChoices {
		return Result{}, fmt.Errorf("provisioning executor returned HTTP %d: %s", response.StatusCode, strings.TrimSpace(string(body)))
	}

	var payload struct {
		Status     string          `json:"status"`
		ExternalID string          `json:"external_id"`
		Config     json.RawMessage `json:"config"`
	}
	if err := json.Unmarshal(body, &payload); err != nil {
		return Result{}, fmt.Errorf("decode executor response: %w", err)
	}
	if payload.ExternalID == "" {
		return Result{}, errors.New("executor response missing external_id")
	}
	config, err := decodeExecutorConfig(payload.Config)
	if err != nil {
		return Result{}, fmt.Errorf("decode executor config: %w", err)
	}

	return Result{Status: payload.Status, ExternalID: payload.ExternalID, Config: config}, nil
}

func decodeExecutorConfig(raw json.RawMessage) (map[string]any, error) {
	if len(raw) == 0 || string(raw) == "null" {
		return map[string]any{}, nil
	}

	var object map[string]any
	if err := json.Unmarshal(raw, &object); err == nil && object != nil {
		return object, nil
	}

	var array []any
	if err := json.Unmarshal(raw, &array); err == nil && len(array) == 0 {
		return map[string]any{}, nil
	}

	return nil, errors.New("expected object")
}
