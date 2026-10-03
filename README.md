# Smart HR System

A professional, self-hosted recruitment and assessment platform powered by AI. Designed for companies like "   Wood and Metal Industries," this system streamlines applicant tracking, generates intelligent interview questions, and provides deep competency analysis using Large Language Models (LLMs).

## 📋 Table of Contents

- [Features](#features)
- [Architecture & Workflow](#architecture--workflow)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [File Structure](#file-structure)
- [Security & Privacy](#security--privacy)
- [Development Notes](#development-notes)

## ✨ Features

### 1. Intelligent Applicant Wizard (`index.php`)
*   **Multi-step Form:** Collects comprehensive personal, educational, and professional data.
*   **File Uploads:** Supports photo and digital signature capture (Canvas API).
*   **AI Interview Generation:** Automatically generates 3 role-specific, behavioral interview questions based on the applicant's profile before submission.

### 2. Secure Submission (`submit.php`)
*   **Atomic Storage:** Saves applicant data to JSON files using atomic writes to prevent data corruption.
*   **Validation:** Strict validation for CSRF tokens, file types, and data structures.
*   **Tracking:** Generates unique tracking codes (e.g., `TOL-20240520-ABC12345`) for every applicant.

### 3. Admin Dashboard (`admin.php`)
*   **Candidate List:** View all applicants with search functionality.
*   **Smart Analysis Engine:**
    *   Analyzes applicant data against 15 professional competencies.
    *   Generates scores (0-100), confidence levels, and evidence-based feedback.
    *   Provides an executive summary and hiring recommendation (`strong_fit`, `fit`, `conditional_fit`, etc.).
*   **Data Isolation:** AI analysis results are stored in a separate `data/analysis/` directory, leaving the original applicant JSON untouched.
*   **Export:** Export candidate data as JSON or CSV.

## 🏗 Architecture & Workflow

The system follows a **Server-Side Rendering (SSR)** pattern with **AJAX** enhancements for dynamic interactions.

1.  **Bootstrap:** `config.php` initializes directories, sessions, and CSRF tokens.
2.  **Frontend:** `index.php` serves the application form. JavaScript handles form validation, file previews, and API calls to `api.php`.
3.  **API Layer:** `api.php` acts as a secure proxy to the LLM provider (GapGPT), ensuring API keys are never exposed to the client.
4.  **Storage:** Data is persisted as structured JSON files in `data/applicants/`.
5.  **Analysis:** The Admin Panel triggers `analyze` actions, which call the LLM to generate competency profiles stored in `data/analysis/`.

## 🛠 Prerequisites

*   **PHP:** Version 8.1 or higher (required for `match`, `str_contains`, and strict types).
*   **Web Server:** Apache or Nginx with `mod_rewrite` (optional but recommended).
*   **Extensions:**
    *   `curl` (for API communication)
    *   `json` (for data handling)
    *   `mbstring` (for multibyte string support)
*   **AI Provider:** Access to an OpenAI-compatible API (e.g., GapGPT, OpenAI, Azure OpenAI).

## 📥 Installation

1.  **Clone/Download:**
    ```bash
    git clone <repository-url>
    cd   -hr
    ```

2.  **Permissions:**
    Ensure the web server user has write permissions to the `data/` directory and its subdirectories.
    ```bash
    chmod -R 750 data/
    chown -R www-data:www-data data/ # Example for Apache/Nginx
    ```

3.  **Configuration:**
    Edit `config.php` to set your API keys and company details.

## ⚙️ Configuration

Open `config.php` and modify the following constants:

```php
// Company Info
const COMPANY_NAME = 'شرکت صنایع چوب و فلز تولیکا';

// AI Provider Settings
const GAPGPT_API_URL = 'https://api.gapgpt.app/v1/chat/completions';
const GAPGPT_API_KEY = 'your-api-key-here';
const GAPGPT_MODEL = 'gpt-4o'; // Or 'gpt-3.5-turbo', 'claude-3', etc.

// Security
const ADMIN_PASSWORD = '  Admin@2026'; // Change this!
```

## 🚀 Usage

### For Applicants
1.  Navigate to `index.php`.
2.  Fill out the personal and professional details.
3.  Upload a photo and sign the form.
4.  Review the AI-generated interview questions and answer them.
5.  Submit the application.

### For HR Administrators
1.  Navigate to `admin.php`.
2.  Log in with the configured `ADMIN_PASSWORD`.
3.  Select an applicant from the sidebar.
4.  Click **"تحلیل هوشمند" (Smart Analysis)** to generate the competency profile.
5.  Review the dashboard for scores, strengths, and recommendations.

## 📂 File Structure

```text
├── admin.php          # Admin dashboard & AI analysis interface
├── api.php            # Backend API for generating interview questions
├── config.php         # Central configuration, helpers, and bootstrap
├── index.php          # Applicant-facing wizard form
├── submit.php         # Backend handler for application submission
├── assets/
│   └── style.css      # Global styles
└── data/              # Runtime data directory (auto-created)
    ├── applicants/    # JSON files for each applicant
    ├── analysis/      # JSON files for AI competency analysis
    ├── logs/          # Application logs
    ├── photos/        # Uploaded candidate photos
    └── signatures/    # Uploaded candidate signatures
```

## 🔒 Security & Privacy

*   **CSRF Protection:** All forms and AJAX requests require a valid CSRF token.
*   **Input Sanitization:** All user inputs are escaped using `htmlspecialchars` before rendering.
*   **API Key Security:** API keys are stored server-side in `config.php` and never exposed to the browser.
*   **Data Isolation:** AI analysis is stored separately from applicant data to maintain data integrity and allow for re-analysis without altering original records.
*   **Ethical AI:** Prompts are explicitly designed to avoid medical, psychological, or discriminatory assessments.

## 💡 Development Notes

*   **No Database:** The system uses JSON files for storage to minimize dependencies. For high-volume deployments, consider migrating to a SQL database.
*   **AI Prompts:** The system uses specific prompts in `config.php` (`buildApplicantAnalysisPrompt`) and `api.php` to guide the LLM. These can be tuned for specific industry needs.
*   **Translation:** A client-side translation script is included in the HTML files for demo purposes. In production, consider using a dedicated i18n library.

## 📄 License

This project is proprietary software for internal use by    Industries. Redistribution or modification without permission is prohibited.
