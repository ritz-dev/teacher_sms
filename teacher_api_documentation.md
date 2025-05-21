# Register

Register a new user with a role and permissions. Returns an access token.

---

## Request

Method: 'POST',  
URL: 'https://api-main-drk8yx.laravel.cloud/gateway/register', 

Headers:  
'Accept: application/json'  
'Content-Type: application/json'

### JSON Body
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "secret123",
  "role_id": "1",
  "employee_id": "EMP-001"
}

| Field         | Type   | Required 
| ------------- | ------ | -------- | 
| `name`        | string | Yes      |          
| `email`       | string | Yes      | 
| `password`    | string | Yes      | 
| `role_id`     | string | Yes      | 
| `employee_id` | string | Yes      | 

## Response
```json

{
    "slug": "ce407823-4a30-481a-bb23-d60c1fac5e99",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiODRmZmNhY2Y5ZGE0ZjI3YTg3YzY5YzBkMjZhY2QwN2RlMjhmNjMyNzA2MzFhNTIyMTY4YzQyNTI0MDU3NmEyMjFkZjY0OWNkOTYwNDg4OWYiLCJpYXQiOjE3NDc3MjM5NzcuMzU5OTUsIm5iZiI6MTc0NzcyMzk3Ny4zNTk5NTIsImV4cCI6MTc3OTI1OTk3Ny4zNTY4OTIsInN1YiI6IjgiLCJzY29wZXMiOltdfQ.hpBE27ItuB9n2KHCeQ_krfVuez9oAzyoABYr3e1KfdxEV-oCXR244h7WeuGm23CJNAse9_F_XBmCEeknxurVR-iXKbcvGxT2sROnvV6it8V2nC1x0z9_aL09dSQ71VVXD7e8LSC6bP84Pb5hqhQZrgJzuI5kWe04xd5vslq53KPAcLlkYAMM-wy11YJ61Xrkt70MfL9CfRg10OtUN7lYabJBjVG_5HhGRDIvY_uS3nP1ZtEcOPK6l5DZPU0eB6cLHhN6GAPN0haKrd7o8epHHkhJJytm0pyzgnnqeXLH8RXwld1DeMIF_K7FAB8df7OApw93ABBOzsErL7f1O2SNlFSA9-_Q-GE1_0mY8cnUwriyaP2rDQ1opekFr6KCJL2EzclO05Qre6CINP-DsKKXa1if3FBdGYflXfz6rZVlucVTMZRSZT1x1B7DfUAC5YUgp40A8wpH6Bft1v4ZUf-5tQXZloCKZNEP82KcwheDnH2AOUFfrQKQ0D9US3NI4u59PzqKDaZ7JRVcAqqSsKpkN1fg-kAbO9-Q_5UgTamh-7MYNx2m37eg8G2cpEySea1_6hOYTPAvD7D4CxPAu2QXJzIxL2aYudXb8bc0xktDAm7mJ3_eHnvm5OCtQr97lr-lCyQJ-eWdjoQvKse5e7jRKkyoBfAdmPtgskIb4xWrOd0",
    "permissions": [
        "user-create",
        "user-read",
        "user-update",
        "user-delete"
    ],
    "role": "Teacher"
}