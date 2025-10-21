# BizAutoPro RESTful API Documentation

## Overview

The BizAutoPro API provides programmatic access to all core system functionality including lead management, inventory control, workflow automation, and user administration. This RESTful API enables mobile app development, third-party integrations, and microservices architecture.

## Base URL
```
http://localhost/bizautopro-system/api/
```

## Authentication

All API endpoints require authentication using Bearer tokens.

### 1. Login and Get Token
```http
POST /api/auth.php
Content-Type: application/json

{
    "username": "your_username",
    "password": "your_password"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "token": "abc123def456...",
        "expires_in": 86400,
        "user": {
            "id": 1,
            "username": "admin",
            "email": "admin@example.com",
            "role": "admin"
        }
    }
}
```

### 2. Using the Token
Include the token in the Authorization header for all subsequent requests:

```http
Authorization: Bearer abc123def456...
```

### 3. Validate Token
```http
GET /api/auth.php
Authorization: Bearer abc123def456...
```

### 4. Logout
```http
DELETE /api/auth.php
Authorization: Bearer abc123def456...
```

---

## Lead Management API

### Get All Leads
```http
GET /api/leads.php?status=new&limit=50&offset=0&search=john
Authorization: Bearer {token}
```

**Query Parameters:**
- `status` (optional): new, contacted, qualified, lost
- `limit` (optional): Max 100, default 50
- `offset` (optional): For pagination, default 0
- `search` (optional): Search in name, email, phone

**Response:**
```json
{
    "success": true,
    "data": {
        "leads": [
            {
                "id": 1,
                "name": "John Doe",
                "email": "john@example.com",
                "phone": "+1234567890",
                "company": "Acme Corp",
                "status": "new",
                "score": 75,
                "assigned_to_name": "Sales Manager",
                "created_at": "2024-01-15 10:30:00"
            }
        ],
        "pagination": {
            "total": 150,
            "limit": 50,
            "offset": 0,
            "has_more": true
        }
    }
}
```

### Get Single Lead
```http
GET /api/leads.php/123
Authorization: Bearer {token}
```

### Create Lead
```http
POST /api/leads.php
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Jane Smith",
    "email": "jane@company.com",
    "phone": "+1987654321",
    "company": "Tech Solutions",
    "status": "new",
    "score": 80,
    "source": "website",
    "notes": "Interested in enterprise package",
    "assigned_to": 5
}
```

### Update Lead
```http
PUT /api/leads.php/123
Authorization: Bearer {token}
Content-Type: application/json

{
    "status": "qualified",
    "score": 90,
    "notes": "Follow-up scheduled for next week"
}
```

### Delete Lead (Admin Only)
```http
DELETE /api/leads.php/123
Authorization: Bearer {token}
```

---

## Inventory Management API

### Get All Inventory Items
```http
GET /api/inventory.php?low_stock=true&search=widget&supplier_id=2
Authorization: Bearer {token}
```

**Query Parameters:**
- `low_stock` (optional): "true" to show only low stock items
- `search` (optional): Search in product name or SKU
- `supplier_id` (optional): Filter by supplier
- `limit`, `offset`: Pagination

**Response:**
```json
{
    "success": true,
    "data": {
        "items": [
            {
                "id": 1,
                "product_name": "Widget Pro",
                "sku": "WGT-PRO-001",
                "quantity": 5,
                "reorder_level": 10,
                "price": 29.99,
                "supplier_name": "Tech Supplies Inc",
                "last_updated": "2024-01-15 14:20:00"
            }
        ],
        "alerts": {
            "low_stock_count": 3
        }
    }
}
```

### Get Single Item with Transaction History
```http
GET /api/inventory.php/123
Authorization: Bearer {token}
```

### Create Inventory Item
```http
POST /api/inventory.php
Authorization: Bearer {token}
Content-Type: application/json

{
    "product_name": "New Widget",
    "sku": "NW-001",
    "quantity": 100,
    "reorder_level": 20,
    "price": 15.50,
    "supplier_id": 3
}
```

### Update Inventory Item
```http
PUT /api/inventory.php/123
Authorization: Bearer {token}
Content-Type: application/json

{
    "quantity": 150,
    "price": 16.00,
    "adjustment_reason": "Stock replenishment"
}
```

### Delete Item (Admin Only)
```http
DELETE /api/inventory.php/123
Authorization: Bearer {token}
```

---

## Inventory Deductions API

### Get Deduction History
```http
GET /api/deductions.php?type=sales&start_date=2024-01-01&end_date=2024-01-31
Authorization: Bearer {token}
```

**Query Parameters:**
- `type` (optional): sales, damaged, internal
- `start_date`, `end_date` (optional): Date range filter
- `limit`, `offset`: Pagination

**Response:**
```json
{
    "success": true,
    "data": {
        "transactions": [
            {
                "id": 1,
                "product_name": "Widget Pro",
                "sku": "WGT-PRO-001",
                "quantity": 5,
                "price": 29.99,
                "type": "sales",
                "username": "sales_user",
                "created_at": "2024-01-15 16:30:00"
            }
        ],
        "statistics": [
            {
                "type": "sales",
                "count": 25,
                "total_quantity": 150,
                "total_value": 4497.50
            }
        ]
    }
}
```

### Process Deduction
```http
POST /api/deductions.php
Authorization: Bearer {token}
Content-Type: application/json

{
    "deduction_type": "sales",
    "reason": "Customer purchase order #12345",
    "items": [
        {
            "id": 1,
            "quantity": 3,
            "price": 29.99
        },
        {
            "id": 5,
            "quantity": 2,
            "price": 15.50
        }
    ]
}
```

**Response:**
```json
{
    "success": true,
    "message": "Deduction processed successfully",
    "data": {
        "deduction_type": "sales",
        "processed_items": [
            {
                "item_id": 1,
                "product_name": "Widget Pro",
                "sku": "WGT-PRO-001",
                "quantity": 3,
                "price": 29.99,
                "line_total": 89.97
            }
        ],
        "total_items": 2,
        "total_quantity": 5,
        "total_value": 120.97,
        "processed_by": "sales_user",
        "processed_at": "2024-01-15 16:45:00"
    }
}
```

---

## Workflow Management API

### Get All Workflows
```http
GET /api/workflows.php?status=pending&assigned_to=5&start_date=2024-01-01
Authorization: Bearer {token}
```

**Query Parameters:**
- `status` (optional): pending, approved, rejected, completed, cancelled
- `assigned_to`, `created_by` (optional): Filter by user ID
- `start_date`, `end_date` (optional): Date range filter

### Get Single Workflow with History
```http
GET /api/workflows.php/123
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "workflow": {
            "id": 123,
            "title": "New Employee Onboarding",
            "description": "Setup equipment and accounts for new hire",
            "status": "pending",
            "priority": "high",
            "creator_name": "HR Manager",
            "assignee_name": "IT Admin",
            "due_date": "2024-01-20",
            "created_at": "2024-01-15 09:00:00"
        },
        "history": [
            {
                "action": "created",
                "username": "hr_manager",
                "timestamp": "2024-01-15 09:00:00"
            }
        ]
    }
}
```

### Create Workflow
```http
POST /api/workflows.php
Authorization: Bearer {token}
Content-Type: application/json

{
    "title": "Equipment Maintenance",
    "description": "Monthly server maintenance and updates",
    "assigned_to": 8,
    "priority": "medium",
    "due_date": "2024-02-01"
}
```

### Update Workflow Status
```http
PUT /api/workflows.php/123
Authorization: Bearer {token}
Content-Type: application/json

{
    "status": "completed",
    "priority": "high"
}
```

### Delete Workflow (Admin Only)
```http
DELETE /api/workflows.php/123
Authorization: Bearer {token}
```

---

## User Management API

### Get All Users (Admin/Manager Only)
```http
GET /api/users.php?status=active&role=employee&search=john
Authorization: Bearer {token}
```

**Query Parameters:**
- `status` (optional): active, pending, suspended, disabled
- `role` (optional): admin, manager, employee, inventory_manager
- `search` (optional): Search in username or email

### Get Single User
```http
GET /api/users.php/123
Authorization: Bearer {token}
```

### Create User (Admin Only)
```http
POST /api/users.php
Authorization: Bearer {token}
Content-Type: application/json

{
    "username": "new_employee",
    "email": "employee@company.com",
    "password": "secure_password123",
    "role": "employee",
    "status": "active"
}
```

### Update User
```http
PUT /api/users.php/123
Authorization: Bearer {token}
Content-Type: application/json

{
    "email": "updated@company.com",
    "role": "manager",
    "status": "active"
}
```

### Delete User (Admin Only)
```http
DELETE /api/users.php/123
Authorization: Bearer {token}
```

---

## Error Responses

All errors follow a consistent format:

```json
{
    "error": true,
    "message": "Error description"
}
```

**Common HTTP Status Codes:**
- `400` - Bad Request (validation errors)
- `401` - Unauthorized (invalid/missing token)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `405` - Method Not Allowed
- `409` - Conflict (duplicate data)
- `500` - Internal Server Error

---

## Rate Limiting & Security

- API tokens expire after 24 hours
- All requests are logged for security monitoring
- Role-based access control enforced on all endpoints
- Input validation and SQL injection protection
- CORS headers enabled for cross-origin requests

---

## Example Mobile App Usage

### JavaScript/React Native Example
```javascript
// Login and store token
const login = async (username, password) => {
    const response = await fetch('http://localhost/bizautopro-system/api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password })
    });
    
    const data = await response.json();
    if (data.success) {
        localStorage.setItem('api_token', data.data.token);
        return data.data.user;
    }
    throw new Error(data.message);
};

// Get leads
const getLeads = async () => {
    const token = localStorage.getItem('api_token');
    const response = await fetch('http://localhost/bizautopro-system/api/leads.php', {
        headers: { 'Authorization': `Bearer ${token}` }
    });
    
    const data = await response.json();
    return data.success ? data.data.leads : [];
};

// Create new lead
const createLead = async (leadData) => {
    const token = localStorage.getItem('api_token');
    const response = await fetch('http://localhost/bizautopro-system/api/leads.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(leadData)
    });
    
    return await response.json();
};
```

### Python Integration Example
```python
import requests

class BizAutoProAPI:
    def __init__(self, base_url):
        self.base_url = base_url
        self.token = None
    
    def login(self, username, password):
        response = requests.post(f"{self.base_url}/auth.php", 
                               json={"username": username, "password": password})
        data = response.json()
        if data.get("success"):
            self.token = data["data"]["token"]
            return data["data"]["user"]
        raise Exception(data.get("message", "Login failed"))
    
    def get_headers(self):
        return {"Authorization": f"Bearer {self.token}"}
    
    def get_inventory(self, **params):
        response = requests.get(f"{self.base_url}/inventory.php", 
                              headers=self.get_headers(), params=params)
        return response.json()
    
    def process_deduction(self, deduction_type, items, reason=None):
        data = {
            "deduction_type": deduction_type,
            "items": items,
            "reason": reason
        }
        response = requests.post(f"{self.base_url}/deductions.php",
                               headers=self.get_headers(), json=data)
        return response.json()

# Usage
api = BizAutoProAPI("http://localhost/bizautopro-system/api")
user = api.login("admin", "password")
inventory = api.get_inventory(low_stock="true")
```

This API layer enables complete integration with external systems while maintaining all existing functionality of the BizAutoPro platform.