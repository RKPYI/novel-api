# Contact API - Quick Reference

## Endpoints at a Glance

### Public
```
POST /api/contact                          Submit contact form (guest or authenticated)
```

### User (Authenticated)
```
GET  /api/my-contacts                      List my contact messages
GET  /api/my-contacts/{contact}            View specific contact (mine only)
```

### Admin Only
```
GET    /api/admin/contacts                 List all contacts
GET    /api/admin/contacts/{contact}       View contact (auto-marks as read)
POST   /api/admin/contacts/{contact}/respond    Send response to user
PUT    /api/admin/contacts/{contact}/status     Update status
DELETE /api/admin/contacts/{contact}       Delete contact
```

## Quick Test Commands

### Submit contact as guest
```bash
curl -X POST http://localhost:8000/api/contact \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "subject": "Test Subject",
    "message": "This is a test message with enough characters."
  }'
```

### Submit contact as authenticated user
```bash
curl -X POST http://localhost:8000/api/contact \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "subject": "Test Subject",
    "message": "This is a test message with enough characters."
  }'
```

### Admin responds to contact
```bash
curl -X POST http://localhost:8000/api/admin/contacts/1/respond \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "admin_response": "Thank you for contacting us. We will look into this issue."
  }'
```

## Notification Flow

### When Contact Submitted:
1. Contact saved to database
2. **All admins** get in-app notification
3. Notification type: `new_contact`

### When Admin Responds:
1. Contact updated with response
2. Status set to `replied`
3. **If registered user**: In-app notification + Email
4. **If guest**: Email only

## Status Values
- `new` - Unread message
- `read` - Viewed by admin
- `replied` - Admin has responded

## Key Features
✅ Silent user capture if authenticated  
✅ Works for both guests and logged-in users  
✅ Admin notifications on new contact  
✅ User notifications on response  
✅ Email integration  
✅ User can view their contact history  
✅ Full response tracking (who, when)

## Database Fields
```php
'user_id'         // NULL for guests, user ID for authenticated
'name'            // Submitter name
'email'           // Submitter email
'subject'         // Message subject
'message'         // Message content
'admin_response'  // Admin's response (NULL until responded)
'responded_by'    // Admin user ID who responded
'responded_at'    // Timestamp of response
'status'          // new|read|replied
'read_at'         // When admin first viewed
```

## Common Use Cases

### User wants to see their contact history
```
GET /api/my-contacts
```

### Admin wants to see unread contacts
```
GET /api/admin/contacts?status=new
```

### Admin wants to respond to contact #5
```
POST /api/admin/contacts/5/respond
Body: { "admin_response": "..." }
```

### Check if user has pending contacts
```
GET /api/my-contacts?status=new
```

## Full Documentation
See `CONTACT_API_DOCUMENTATION.md` for complete details.
