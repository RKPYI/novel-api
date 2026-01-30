# Contact API Documentation

## Overview
The Contact API allows users (both authenticated and guests) to submit contact form messages. The system automatically captures user information if they're logged in and provides a complete notification system for both admins and users.

## Features

- ✅ **Silent User Detection**: Automatically captures user data if authenticated
- ✅ **Admin Notifications**: All admins receive in-app notifications for new contacts
- ✅ **User Notifications**: Users receive notifications when admins respond
- ✅ **Email Integration**: Email notifications sent to both registered and guest users
- ✅ **Response Tracking**: Full tracking of who responded and when
- ✅ **User Portal**: Authenticated users can view their contact history

## Public Endpoint

### Submit Contact Form
Submit a contact form message. If the user is authenticated, their user_id will be automatically captured.

**Endpoint:** `POST /api/contact`

**Authentication:** Optional (works for both guests and authenticated users)

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "subject": "Feature Request",
  "message": "I would like to suggest a new feature for the platform."
}
```

**Behavior:**
- If user is **not authenticated**: Creates contact with only provided information
- If user is **authenticated**: Silently captures `user_id` for linking

**Validation Rules:**
- `name`: Required, string, max 255 characters
- `email`: Required, valid email address, max 255 characters
- `subject`: Required, string, max 255 characters
- `message`: Required, string, min 10 characters, max 5000 characters

**Success Response (201 Created):**
```json
{
  "success": true,
  "message": "Your message has been sent successfully. We'll get back to you soon!"
}
```

**Side Effects:**
- Contact record created in database
- All admins receive an in-app notification
- Contact submission logged

**Validation Error Response (422 Unprocessable Entity):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field must be a valid email address."],
    "subject": ["The subject field is required."],
    "message": ["The message field must be at least 10 characters."]
  }
}
```

## User Endpoints (Authenticated)

### Get My Contacts
Get all contact messages submitted by the authenticated user.

**Endpoint:** `GET /api/my-contacts`

**Authentication:** Required

**Query Parameters:**
- `per_page` (optional): Number of items per page (default: 15)
- `page` (optional): Page number

**Example Request:**
```bash
GET /api/my-contacts?per_page=10
```

**Response (200 OK):**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 5,
      "user_id": 10,
      "name": "John Doe",
      "email": "john@example.com",
      "subject": "Feature Request",
      "message": "I would like to suggest...",
      "admin_response": "Thank you for your suggestion. We'll consider it for our next release.",
      "responded_by": 2,
      "responded_at": "2026-01-09T16:30:00.000000Z",
      "status": "replied",
      "read_at": "2026-01-09T16:25:00.000000Z",
      "created_at": "2026-01-09T16:17:58.000000Z",
      "updated_at": "2026-01-09T16:30:00.000000Z",
      "responder": {
        "id": 2,
        "name": "Admin User",
        "email": "admin@example.com"
      }
    }
  ],
  "total": 3
}
```

### Get Specific Contact (User's Own)
Get details of a specific contact message (user can only view their own).

**Endpoint:** `GET /api/my-contacts/{contact}`

**Authentication:** Required

**Response (200 OK):**
```json
{
  "id": 5,
  "user_id": 10,
  "name": "John Doe",
  "email": "john@example.com",
  "subject": "Feature Request",
  "message": "I would like to suggest a new feature...",
  "admin_response": "Thank you for your suggestion. We'll consider it for our next release.",
  "responded_by": 2,
  "responded_at": "2026-01-09T16:30:00.000000Z",
  "status": "replied",
  "read_at": "2026-01-09T16:25:00.000000Z",
  "created_at": "2026-01-09T16:17:58.000000Z",
  "updated_at": "2026-01-09T16:30:00.000000Z",
  "responder": {
    "id": 2,
    "name": "Admin User",
    "email": "admin@example.com",
    "role": "admin"
  }
}
```

**Error Response (403 Forbidden):**
```json
{
  "success": false,
  "message": "Unauthorized"
}
```

## Admin Endpoints

All admin endpoints require authentication with the `admin` role.

### List All Contacts
Get paginated list of all contact messages with user and responder information.

**Endpoint:** `GET /api/admin/contacts`

**Authentication:** Required (Admin only)

**Query Parameters:**
- `per_page` (optional): Number of items per page (default: 15)
- `status` (optional): Filter by status (`new`, `read`, `replied`)
- `page` (optional): Page number

**Example Request:**
```bash
GET /api/admin/contacts?status=new&per_page=20
```

**Response (200 OK):**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 3,
      "user_id": null,
      "name": "Jane Smith",
      "email": "jane@example.com",
      "subject": "Bug Report",
      "message": "I found a bug...",
      "admin_response": null,
      "responded_by": null,
      "responded_at": null,
      "status": "new",
      "read_at": null,
      "created_at": "2026-01-09T17:11:37.000000Z",
      "updated_at": "2026-01-09T17:11:37.000000Z",
      "user": null,
      "responder": null
    },
    {
      "id": 2,
      "user_id": 10,
      "name": "John Doe",
      "email": "john@example.com",
      "subject": "Feature Request",
      "message": "I would like to suggest...",
      "admin_response": "We'll consider this.",
      "responded_by": 2,
      "responded_at": "2026-01-09T16:30:00.000000Z",
      "status": "replied",
      "read_at": "2026-01-09T16:25:00.000000Z",
      "created_at": "2026-01-09T16:17:58.000000Z",
      "updated_at": "2026-01-09T16:30:00.000000Z",
      "user": {
        "id": 10,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "user"
      },
      "responder": {
        "id": 2,
        "name": "Admin User",
        "email": "admin@example.com",
        "role": "admin"
      }
    }
  ],
  "total": 2
}
```

### View Contact Details
Get details of a specific contact message. **Automatically marks the message as read.**

**Endpoint:** `GET /api/admin/contacts/{contact}`

**Authentication:** Required (Admin only)

**Response (200 OK):**
```json
{
  "id": 1,
  "user_id": 10,
  "name": "John Doe",
  "email": "john@example.com",
  "subject": "Feature Request",
  "message": "I would like to suggest a new feature for the platform.",
  "admin_response": null,
  "responded_by": null,
  "responded_at": null,
  "status": "read",
  "read_at": "2026-01-09T16:20:00.000000Z",
  "created_at": "2026-01-09T16:17:58.000000Z",
  "updated_at": "2026-01-09T16:20:00.000000Z",
  "user": {
    "id": 10,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "user"
  },
  "responder": null
}
```

### Respond to Contact
Send a response to a contact message. This will:
- Update the contact with admin response
- Set status to `replied`
- Send notification to user (in-app if registered, email if not)
- Record who responded and when

**Endpoint:** `POST /api/admin/contacts/{contact}/respond`

**Authentication:** Required (Admin only)

**Request Body:**
```json
{
  "admin_response": "Thank you for your message. We have reviewed your request and will implement this feature in our next release."
}
```

**Validation Rules:**
- `admin_response`: Required, string, min 10 characters, max 5000 characters

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Response sent successfully",
  "contact": {
    "id": 1,
    "user_id": 10,
    "name": "John Doe",
    "email": "john@example.com",
    "subject": "Feature Request",
    "message": "I would like to suggest...",
    "admin_response": "Thank you for your message. We have reviewed your request...",
    "responded_by": 2,
    "responded_at": "2026-01-09T16:30:00.000000Z",
    "status": "replied",
    "read_at": "2026-01-09T16:20:00.000000Z",
    "created_at": "2026-01-09T16:17:58.000000Z",
    "updated_at": "2026-01-09T16:30:00.000000Z",
    "user": {
      "id": 10,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "responder": {
      "id": 2,
      "name": "Admin User",
      "email": "admin@example.com"
    }
  }
}
```

**Side Effects:**
- Contact status changed to `replied`
- `responded_by`, `responded_at`, and `admin_response` fields updated
- **Registered users**: Receive in-app notification AND email
- **Guest users**: Receive email to the provided email address
- Response logged

### Update Contact Status
Manually update the status of a contact message.

**Endpoint:** `PUT /api/admin/contacts/{contact}/status`

**Authentication:** Required (Admin only)

**Request Body:**
```json
{
  "status": "read"
}
```

**Valid Status Values:**
- `new`: New, unread message
- `read`: Message has been read
- `replied`: Response has been sent to the user

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Contact status updated successfully",
  "contact": {
    "id": 1,
    "status": "read",
    ...
  }
}
```

### Delete Contact Message
Delete a contact message permanently.

**Endpoint:** `DELETE /api/admin/contacts/{contact}`

**Authentication:** Required (Admin only)

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Contact message deleted successfully"
}
```

## Notification System

### Admin Notifications

When a new contact is submitted:

**In-App Notification (stored in `notifications` table):**
```json
{
  "type": "new_contact",
  "title": "New Contact Message",
  "message": "New contact message from Jane Smith: Bug Report",
  "data": {
    "contact_id": 3,
    "name": "Jane Smith",
    "email": "jane@example.com",
    "subject": "Bug Report",
    "is_registered_user": false
  }
}
```

**Notification sent to:** All users with `role = 'admin'`

### User Notifications

When an admin responds to a contact:

**For Registered Users (In-App + Email):**

In-App Notification:
```json
{
  "type": "contact_response",
  "title": "Response to Your Contact Message",
  "message": "We have responded to your message: Feature Request",
  "data": {
    "contact_id": 1,
    "subject": "Feature Request",
    "admin_response": "Thank you for your suggestion...",
    "responded_at": "2026-01-09T16:30:00.000000Z"
  }
}
```

Email:
```
Subject: Response to Your Contact Message - [App Name]

Hello John Doe!

We have responded to your contact message regarding: Feature Request

Our response:
Thank you for your message. We have reviewed your request and will implement this feature in our next release.

If you have any further questions, please feel free to contact us again.

Regards,
[App Name] Team
```

**For Guest Users (Email Only):**
Same email content sent to the email address provided in the contact form.

## Contact Model

### Database Schema

```sql
CREATE TABLE contacts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NULL,  -- Links to users table if authenticated
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    admin_response TEXT NULL,  -- Admin's response
    responded_by BIGINT NULL,  -- Admin who responded
    responded_at TIMESTAMP NULL,
    status VARCHAR(255) DEFAULT 'new',
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### Status Constants
- `Contact::STATUS_NEW` = 'new'
- `Contact::STATUS_READ` = 'read'
- `Contact::STATUS_REPLIED` = 'replied'

### Methods
- `isNew()`: Check if contact is new
- `isRead()`: Check if contact has been read
- `isReplied()`: Check if contact has been replied to
- `isFromRegisteredUser()`: Check if contact is from a registered user
- `markAsRead()`: Mark contact as read (sets status and read_at timestamp)

### Relationships
- `user()`: BelongsTo User (the person who submitted the contact)
- `responder()`: BelongsTo User (the admin who responded)

## Usage Examples

### Frontend - Submit Contact Form (Guest)

```javascript
async function submitContactForm(formData) {
  try {
    const response = await fetch('/api/contact', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(formData),
    });

    const data = await response.json();

    if (data.success) {
      console.log('Success:', data.message);
    } else {
      console.error('Error:', data.message);
    }
  } catch (error) {
    console.error('Network error:', error);
  }
}
```

### Frontend - Submit Contact Form (Authenticated User)

```javascript
async function submitContactForm(formData, authToken) {
  try {
    const response = await fetch('/api/contact', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${authToken}`, // User will be auto-linked
      },
      body: JSON.stringify(formData),
    });

    const data = await response.json();

    if (data.success) {
      console.log('Success:', data.message);
      // User can later view this contact in their dashboard
    }
  } catch (error) {
    console.error('Network error:', error);
  }
}
```

### User - View My Contacts

```javascript
async function getMyContacts(token) {
  const response = await fetch('/api/my-contacts', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  const data = await response.json();
  
  data.data.forEach(contact => {
    console.log(`Subject: ${contact.subject}`);
    console.log(`Status: ${contact.status}`);
    if (contact.admin_response) {
      console.log(`Response: ${contact.admin_response}`);
    }
  });
}
```

### Admin - Respond to Contact

```javascript
async function respondToContact(contactId, response, adminToken) {
  const apiResponse = await fetch(`/api/admin/contacts/${contactId}/respond`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${adminToken}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({
      admin_response: response
    }),
  });

  const data = await apiResponse.json();
  
  if (data.success) {
    console.log('Response sent! User will be notified.');
  }
}
```

### Admin - Get Contacts with Filters

```javascript
async function getContacts(adminToken, filters = {}) {
  const params = new URLSearchParams();
  
  if (filters.status) params.append('status', filters.status);
  if (filters.perPage) params.append('per_page', filters.perPage);
  if (filters.page) params.append('page', filters.page);

  const response = await fetch(`/api/admin/contacts?${params}`, {
    headers: {
      'Authorization': `Bearer ${adminToken}`,
      'Accept': 'application/json',
    },
  });

  return await response.json();
}

// Usage
const newContacts = await getContacts(token, { status: 'new' });
const allContacts = await getContacts(token, { perPage: 50, page: 1 });
```

## Testing

### cURL Examples

**Submit Contact Form (Guest):**
```bash
curl -X POST http://localhost:8000/api/contact \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "subject": "Feature Request",
    "message": "I would like to suggest a new feature for the platform."
  }'
```

**Submit Contact Form (Authenticated):**
```bash
curl -X POST http://localhost:8000/api/contact \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "subject": "Feature Request",
    "message": "I would like to suggest a new feature."
  }'
```

**Get My Contacts (User):**
```bash
curl -X GET http://localhost:8000/api/my-contacts \
  -H "Authorization: Bearer YOUR_USER_TOKEN" \
  -H "Accept: application/json"
```

**Get All Contacts (Admin):**
```bash
curl -X GET "http://localhost:8000/api/admin/contacts?status=new" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Accept: application/json"
```

**Respond to Contact (Admin):**
```bash
curl -X POST http://localhost:8000/api/admin/contacts/1/respond \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "admin_response": "Thank you for your message. We will implement this feature soon."
  }'
```

**Update Contact Status (Admin):**
```bash
curl -X PUT http://localhost:8000/api/admin/contacts/1/status \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"status": "read"}'
```

## Workflow Examples

### Complete User Journey (Registered User)

1. **User submits contact form** while logged in
   - User data (`user_id`) automatically captured
   - Contact saved with status `new`
   - All admins receive notification

2. **User checks their contact history**
   - GET `/api/my-contacts`
   - Sees their message with status `new`

3. **Admin receives notification**
   - Views in-app notification
   - Clicks to view contact details
   - Contact status changes to `read`

4. **Admin responds**
   - POST `/api/admin/contacts/{id}/respond`
   - Admin response saved
   - Status changes to `replied`

5. **User receives notification**
   - In-app notification appears
   - Email sent to user's registered email
   - User can view response in `/api/my-contacts`

### Complete User Journey (Guest User)

1. **Guest submits contact form**
   - No authentication token
   - Contact saved with `user_id = null`
   - All admins receive notification

2. **Admin responds**
   - POST `/api/admin/contacts/{id}/respond`
   - Since no `user_id`, email sent directly to contact email

3. **Guest receives email**
   - Email with admin response sent
   - Guest can reply via email or submit new contact

## Security Considerations

- ✅ Users can only view their own contacts
- ✅ Only admins can view all contacts
- ✅ Only admins can respond to contacts
- ✅ Rate limiting recommended for public endpoint
- ✅ Input validation on all fields
- ✅ XSS protection through proper escaping
- ✅ SQL injection prevention via Eloquent ORM

## Notes

- Contact submission is a **public endpoint** (works without authentication)
- If user is authenticated, their `user_id` is **silently captured**
- **All admins** receive in-app notifications for new contacts
- **Registered users** receive both in-app and email notifications for responses
- **Guest users** receive email notifications only
- Admin responses are tracked with timestamp and admin ID
- Contacts from registered users can be viewed by those users in their dashboard
- The system logs all contact submissions and responses for audit purposes


## Public Endpoint

### Submit Contact Form
Submit a contact form message.

**Endpoint:** `POST /api/contact`

**Authentication:** Not required (public endpoint)

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "subject": "Feature Request",
  "message": "I would like to suggest a new feature for the platform."
}
```

**Validation Rules:**
- `name`: Required, string, max 255 characters
- `email`: Required, valid email address, max 255 characters
- `subject`: Required, string, max 255 characters
- `message`: Required, string, min 10 characters, max 5000 characters

**Success Response (201 Created):**
```json
{
  "success": true,
  "message": "Your message has been sent successfully. We'll get back to you soon!"
}
```

**Validation Error Response (422 Unprocessable Entity):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field must be a valid email address."],
    "subject": ["The subject field is required."],
    "message": ["The message field must be at least 10 characters."]
  }
}
```

**Server Error Response (500 Internal Server Error):**
```json
{
  "success": false,
  "message": "Failed to send message. Please try again later."
}
```

## Admin Endpoints

All admin endpoints require authentication with the `admin` role.

### List All Contacts
Get paginated list of all contact messages.

**Endpoint:** `GET /api/admin/contacts`

**Authentication:** Required (Admin only)

**Query Parameters:**
- `per_page` (optional): Number of items per page (default: 15)
- `status` (optional): Filter by status (`new`, `read`, `replied`)
- `page` (optional): Page number

**Example Request:**
```bash
GET /api/admin/contacts?status=new&per_page=20
```

**Response (200 OK):**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "subject": "Feature Request",
      "message": "I would like to suggest...",
      "status": "new",
      "read_at": null,
      "created_at": "2026-01-09T16:17:58.000000Z",
      "updated_at": "2026-01-09T16:17:58.000000Z"
    }
  ],
  "first_page_url": "http://localhost:8000/api/admin/contacts?page=1",
  "from": 1,
  "last_page": 1,
  "last_page_url": "http://localhost:8000/api/admin/contacts?page=1",
  "links": [...],
  "next_page_url": null,
  "path": "http://localhost:8000/api/admin/contacts",
  "per_page": 15,
  "prev_page_url": null,
  "to": 1,
  "total": 1
}
```

### View Contact Details
Get details of a specific contact message. Automatically marks the message as read.

**Endpoint:** `GET /api/admin/contacts/{contact}`

**Authentication:** Required (Admin only)

**Response (200 OK):**
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "subject": "Feature Request",
  "message": "I would like to suggest a new feature for the platform.",
  "status": "read",
  "read_at": "2026-01-09T16:20:00.000000Z",
  "created_at": "2026-01-09T16:17:58.000000Z",
  "updated_at": "2026-01-09T16:20:00.000000Z"
}
```

### Update Contact Status
Update the status of a contact message.

**Endpoint:** `PUT /api/admin/contacts/{contact}/status`

**Authentication:** Required (Admin only)

**Request Body:**
```json
{
  "status": "replied"
}
```

**Valid Status Values:**
- `new`: New, unread message
- `read`: Message has been read
- `replied`: Response has been sent to the user

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Contact status updated successfully",
  "contact": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "subject": "Feature Request",
    "message": "I would like to suggest...",
    "status": "replied",
    "read_at": "2026-01-09T16:20:00.000000Z",
    "created_at": "2026-01-09T16:17:58.000000Z",
    "updated_at": "2026-01-09T16:25:00.000000Z"
  }
}
```

### Delete Contact Message
Delete a contact message.

**Endpoint:** `DELETE /api/admin/contacts/{contact}`

**Authentication:** Required (Admin only)

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Contact message deleted successfully"
}
```

## Contact Model

### Status Constants
- `Contact::STATUS_NEW` = 'new'
- `Contact::STATUS_READ` = 'read'
- `Contact::STATUS_REPLIED` = 'replied'

### Methods
- `isNew()`: Check if contact is new
- `isRead()`: Check if contact has been read
- `isReplied()`: Check if contact has been replied to
- `markAsRead()`: Mark contact as read (sets status and read_at timestamp)

## Database Schema

```sql
CREATE TABLE contacts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(255) DEFAULT 'new',
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

## Usage Examples

### Frontend - Submit Contact Form

```javascript
async function submitContactForm(formData) {
  try {
    const response = await fetch('/api/contact', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(formData),
    });

    const data = await response.json();

    if (data.success) {
      console.log('Success:', data.message);
      // Show success message to user
    } else {
      console.error('Error:', data.message);
      // Show validation errors
    }
  } catch (error) {
    console.error('Network error:', error);
  }
}

// Usage
submitContactForm({
  name: 'John Doe',
  email: 'john@example.com',
  subject: 'Feature Request',
  message: 'I would like to suggest a new feature...'
});
```

### Admin - Fetch Contact Messages

```javascript
async function getContacts(page = 1, status = null) {
  const params = new URLSearchParams({ page });
  if (status) params.append('status', status);

  const response = await fetch(`/api/admin/contacts?${params}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  return await response.json();
}
```

### Admin - Update Contact Status

```javascript
async function updateContactStatus(contactId, newStatus) {
  const response = await fetch(`/api/admin/contacts/${contactId}/status`, {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ status: newStatus }),
  });

  return await response.json();
}
```

## Testing

### cURL Examples

**Submit Contact Form:**
```bash
curl -X POST http://localhost:8000/api/contact \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "subject": "Feature Request",
    "message": "I would like to suggest a new feature for the platform."
  }'
```

**Get All Contacts (Admin):**
```bash
curl -X GET http://localhost:8000/api/admin/contacts \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Accept: application/json"
```

**Update Contact Status (Admin):**
```bash
curl -X PUT http://localhost:8000/api/admin/contacts/1/status \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"status": "replied"}'
```

## Notes

- Contact submission is a **public endpoint** and does not require authentication
- All contact messages are logged for security and debugging purposes
- Contact messages are stored in the database with timestamps
- Admin can filter contacts by status, view details, update status, and delete messages
- When an admin views a contact message, it's automatically marked as read
- The endpoint includes comprehensive validation and error handling
- Optional email notification to admin can be enabled by implementing the `sendAdminNotification()` method
