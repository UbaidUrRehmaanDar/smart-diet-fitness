# Smart Diet & Fitness Recommendation System — Project Overview

## Quick Summary

A web-based app that gives users personalized diet and fitness plans, tracks their progress, and sends reminders. Built as a Final Year Project (BS Computer Science, 2022–2026) at Govt. Postgraduate Jahanzeb College, Swat.

**Students:** Wajeeha Younas (2261113), Hareem Aziz (2261100)  
**Supervisor:** Assistant Professor Younas Ali

---

## Tech Stack

| Layer     | Technology        |
|-----------|-------------------|
| Frontend  | HTML, CSS, JS     |
| Backend   | PHP               |
| Database  | MySQL (implied)   |

> Keep everything as **simple as possible**. No frameworks unless absolutely necessary.

---

## Core Features

### 1. User Management
- Register with name, email, password
- Login / logout
- Profile setup: age, gender, weight, height, activity level, health goals
- Password reset via email

### 2. Data Collection
- Dietary habits: meal preferences, allergies, food restrictions
- Health parameters: BMI, calorie intake, medical conditions
- Log daily meals and workouts

### 3. Recommendation Engine
- Generate personalized daily diet plans based on user profile
- Generate personalized workout plans based on fitness level
- Suggest calorie targets per meal and per day
- Update recommendations as user progress changes

### 4. Notification System
- Meal reminders at scheduled times
- Workout reminders
- Hydration reminders throughout the day
- Users can customize or turn off notifications

### 5. Progress Tracking Dashboard
- Visual dashboard: weight progress, calories consumed, workouts completed
- Weekly and monthly progress reports
- Milestone achievements (e.g., "Lost 5kg!", "7-day streak!")
- Historical data and trends

### 6. Motivational Features
- Daily motivational tips and quotes
- Badges/achievements when milestones are reached

---

## Non-Functional Requirements

| Category        | Requirement                                                  |
|-----------------|--------------------------------------------------------------|
| Performance     | Pages load in < 3s; recommendations generated in < 5s        |
| Security        | Passwords hashed (bcrypt); HTTPS; role-based access control  |
| Usability       | Simple UI, responsive (desktop + mobile)                     |
| Reliability     | 99% uptime; data backup; graceful failure recovery           |
| Scalability     | Architecture should support growth; DB handles large data    |
| Maintainability | Clean, modular, well-documented code                         |

---

## Project Timeline (10 Months)

| Phase                        | Months  | Deliverable                        |
|------------------------------|---------|------------------------------------|
| Requirements & Literature    | 1       | Requirements Document              |
| System Design & Prototype    | 2       | Wireframes & Design Document       |
| Backend & Database           | 3–4     | Database schema & PHP APIs         |
| Frontend & Web App           | 5–6     | Working App Prototype              |
| Integration & Testing        | 7–8     | Test Reports                       |
| Final Evaluation & Docs      | 9–10    | Final Report & Presentation        |

---

## Deliverables

1. Web app with registration, login, and profile setup
2. Recommendation engine for diet and fitness plans
3. Notification system for meal/workout/hydration reminders
4. Progress tracking dashboard with visual reports
5. Final documentation and presentation

---

## Key Constraints for the Agent

- **Stack is strictly HTML + CSS + JS + PHP** — no React, no Node, no frameworks
- Keep the codebase **modular and simple** — each feature should be an independent PHP module
- Passwords must be hashed with **bcrypt** (`password_hash()` in PHP)
- UI must be **responsive** (mobile + desktop)
- Recommendation logic can be **rule-based** (simple if/else or lookup tables) — no ML required
