# 📘 SMART - System for Continuous Parent Engagement (SCOPE)

## Overview
Full-stack parental engagement system with real-time notifications, progress tracking, and AI-powered insights.

## Tech Stack
- **Backend**: Node.js + Express + MySQL
- **Frontend**: React.js
- **Database**: MySQL 8.0
- **Authentication**: JWT
- **Notifications**: Firebase FCM + SMS
- **Deployment**: AWS EC2 + RDS

## Core Features
1. Real-time attendance notifications
2. Result and progress sharing
3. Parent-teacher communication hub
4. AI-based suggestions
5. Multilingual support
6. School-parent updates

## System Architecture
```
[Mobile App/Web] → [Load Balancer] → [Express API] → [MySQL RDS]
                                   ↓
                              [FCM/SMS Service]
                                   ↓
                              [AI Service]
```

## User Roles
- **Admin**: School management
- **Teacher**: Class management, attendance, results
- **Parent**: View child progress, communicate
- **Student**: Basic profile access