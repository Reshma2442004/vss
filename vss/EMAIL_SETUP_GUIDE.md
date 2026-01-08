# VSS Hostel Management - Email System Setup Guide

## 📧 Complete PHPMailer Email System

This guide will help you set up the email functionality for sending emails from Admin Dashboard to Rectors.

## 🚀 Quick Setup Steps

### 1. **Create Email Database Table**
Visit: `http://localhost/vss/vss/create_email_table.php`
- This creates the `email_logs` table for tracking sent emails

### 2. **Configure Email Settings**
Edit: `config/email_config.php`

**For Hostinger:**
```php
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'admin@yourdomain.com'); // Your email
define('SMTP_PASSWORD', 'your_email_password');   // Your password
define('SMTP_FROM_EMAIL', 'admin@yourdomain.com');
define('SMTP_FROM_NAME', 'VSS Hostel Management');
```

**For Gmail:**
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_gmail@gmail.com');
define('SMTP_PASSWORD', 'your_app_password');     // Use App Password
define('SMTP_FROM_EMAIL', 'your_gmail@gmail.com');
define('SMTP_FROM_NAME', 'VSS Hostel Management');
```

### 3. **Gmail App Password Setup** (if using Gmail)
1. Go to Google Account settings
2. Enable 2-Factor Authentication
3. Generate App Password for "Mail"
4. Use this App Password in config (not your regular password)

### 4. **Test Email System**
1. Login as Admin: `http://localhost/vss/vss/auth/admin_login.php`
2. Go to Rector Management section
3. Click "Send Email" button next to any rector
4. Fill form and send test email

## 📋 Features Included

### ✅ **Admin Dashboard Integration**
- Send Email button for each rector
- Professional email form with subject, message, attachments
- Real-time sending status with loading indicators

### ✅ **PHPMailer Integration**
- Automatic PHPMailer download and setup
- Secure SMTP authentication
- HTML email templates with VSS branding
- File attachment support (PDF, DOC, Images, TXT)

### ✅ **Email Templates**
- Professional HTML email design
- VSS Hostel Management branding
- Responsive email layout
- Plain text fallback

### ✅ **Database Logging**
- All emails logged in `email_logs` table
- Track sent/failed emails
- Error message logging
- Sender and recipient tracking

### ✅ **Security Features**
- Admin-only access (super_admin role required)
- File type validation for attachments
- File size limits (5MB per attachment)
- Input sanitization and validation

### ✅ **Error Handling**
- Comprehensive error messages
- Network error handling
- SMTP connection error handling
- User-friendly error notifications

## 🔧 File Structure

```
vss/
├── config/
│   └── email_config.php          # SMTP configuration
├── handlers/
│   └── send_email.php            # Email sending logic
├── dashboards/
│   └── super_admin.php           # Admin dashboard with email UI
├── vendor/
│   └── phpmailer/                # Auto-downloaded PHPMailer
├── uploads/
│   └── email_attachments/        # Temporary attachment storage
└── create_email_table.php        # Database setup
```

## 🎯 Usage Instructions

### **For Admin:**
1. Login to Admin Dashboard
2. Navigate to "Rector Management" section
3. Click "Send Email" button next to rector name
4. Fill in:
   - Subject line
   - Email message
   - Optional file attachments
5. Click "Send Email"
6. Get instant success/error feedback

### **Email Features:**
- **Rich Text**: Supports line breaks and formatting
- **Attachments**: Multiple files up to 5MB each
- **Professional Design**: VSS branded email template
- **Delivery Tracking**: All emails logged in database
- **Error Logging**: Failed emails tracked with error details

## 🛠️ Troubleshooting

### **Common Issues:**

**1. "SMTP Error" - Authentication Failed**
- Check email credentials in `config/email_config.php`
- For Gmail: Use App Password, not regular password
- For Hostinger: Use cPanel email credentials

**2. "Connection Timeout"**
- Check SMTP host and port settings
- Verify server allows outbound SMTP connections
- Try different ports (587, 465, 25)

**3. "File Upload Error"**
- Check file size (max 5MB per file)
- Verify file types (PDF, DOC, Images, TXT only)
- Ensure uploads folder has write permissions

**4. "PHPMailer Not Found"**
- System auto-downloads PHPMailer on first use
- Check internet connection for download
- Verify vendor folder has write permissions

### **Testing Steps:**
1. Send test email to your own email first
2. Check spam/junk folder if not received
3. Verify email logs in database: `SELECT * FROM email_logs ORDER BY sent_at DESC`
4. Check error messages in email_logs table

## 📊 Database Schema

**email_logs table:**
```sql
CREATE TABLE email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,           -- Admin user ID
    recipient_id INT NOT NULL,        -- Rector user ID  
    subject VARCHAR(255) NOT NULL,    -- Email subject
    message TEXT NOT NULL,            -- Email content
    sent_at TIMESTAMP DEFAULT NOW,    -- Send timestamp
    status ENUM('sent', 'failed'),    -- Delivery status
    error_message TEXT NULL           -- Error details if failed
);
```

## 🔐 Security Notes

- Only super_admin role can send emails
- All file uploads are validated and temporary
- Email content is sanitized before sending
- SMTP credentials should be kept secure
- Email logs help track all communications

## 🌟 Production Deployment

**For Hostinger/Live Server:**
1. Update `config/email_config.php` with live email credentials
2. Use your domain email (e.g., admin@yourdomain.com)
3. Test email delivery to external addresses
4. Monitor email logs for delivery issues
5. Set up email backup/forwarding if needed

**Email Best Practices:**
- Use professional email addresses
- Keep subject lines clear and descriptive
- Include contact information in emails
- Monitor delivery rates and spam reports
- Regular backup of email logs

---

## 🎉 Ready to Use!

Your VSS Hostel Management System now has complete email functionality. Admins can send professional emails to rectors with attachments, full delivery tracking, and comprehensive error handling.

**Need Help?** Check the troubleshooting section or verify your SMTP settings.