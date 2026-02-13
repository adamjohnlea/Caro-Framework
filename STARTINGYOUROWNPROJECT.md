# Starting Your Own Project with Caro Framework

Welcome! This guide will walk you through everything you need to know to build your own web application using Caro Framework. Don't worry if you're not super technical - we'll explain everything step by step.

## Table of Contents
- [What You're Building](#what-youre-building)
- [What You'll Need](#what-youll-need)
- [Initial Setup](#initial-setup)
- [Understanding Modules](#understanding-modules)
- [Configuring Your Project](#configuring-your-project)
- [Building Your First Feature](#building-your-first-feature)
- [Common Tasks](#common-tasks)
- [Troubleshooting](#troubleshooting)

---

## What You're Building

Caro Framework helps you build web applications - websites that users can log into, interact with, and use to get things done. Think of it like having a starter kit that already includes:

- **User login system** - Let people create accounts and sign in
- **Email sending** - Send emails to your users
- **Background jobs** - Do tasks automatically without making users wait
- **Database** - Store your data safely

You can turn on or off any of these features depending on what you need.

---

## What You'll Need

Before starting, make sure you have these tools installed on your computer:

### Required Software

1. **PHP 8.4** - The programming language Caro uses
   - **What it does**: Runs your website's code
   - **How to get it**: Install [Laravel Herd](https://herd.laravel.com/) (easiest option - includes everything you need)

2. **Composer** - Manages PHP code libraries
   - **What it does**: Downloads and updates code packages your project needs
   - **How to get it**: Comes with Laravel Herd, or download from [getcomposer.org](https://getcomposer.org/)

3. **Node.js & npm** - Manages frontend code
   - **What it does**: Helps build your website's styling and interactive features
   - **How to get it**: Download from [nodejs.org](https://nodejs.org/) (get the LTS version)

### Optional But Recommended

- A code editor like [VS Code](https://code.visualstudio.com/) to view and edit your project files
- Basic familiarity with using the command line (Terminal on Mac, Command Prompt on Windows)

---

## Initial Setup

Let's get your project up and running! Follow these steps carefully:

### Step 1: Get the Framework

First, you need to copy the Caro Framework to your computer.

```bash
# Navigate to where you want your project
cd ~/Projects

# Clone the framework
git clone https://github.com/your-repo/caro-framework.git my-awesome-project

# Go into your new project folder
cd my-awesome-project
```

**What just happened?** You copied all the framework files to a new folder called "my-awesome-project" on your computer.

### Step 2: Install Dependencies

Now we need to download all the code packages your project needs.

```bash
# Install PHP packages AND set up quality checks
composer setup
```

Or if you prefer to do it step by step:

```bash
# Install PHP packages
composer install

# Install JavaScript packages
npm install

# Set up automatic quality checks (highly recommended!)
composer hooks:install
```

**What just happened?**
- `composer setup` (or `composer install`) downloaded PHP libraries your project needs
- `npm install` downloaded JavaScript tools for styling
- `composer hooks:install` set up a "pre-commit hook" - this automatically checks your code quality before you save changes to git, catching errors early

**Why are git hooks important?** They prevent you from accidentally committing code that has errors or doesn't follow the project's style guidelines. Every time you try to commit code, it will run tests and style checks first. If something is wrong, the commit is blocked and you'll see what needs to be fixed.

**This might take a few minutes** - you'll see lots of text scrolling by. That's normal!

### Step 3: Create Your Configuration File

```bash
# Copy the example configuration
cp .env.example .env
```

**What just happened?** You created a `.env` file - this is where you'll store all your project's settings (like passwords, which features to turn on, etc.)

### Step 3.5: Create the Database File

```bash
# Create the database file
touch storage/database.sqlite

# Make sure the storage directory is writable
chmod -R 775 storage
```

**What just happened?** You created an empty SQLite database file. The framework will automatically create the tables it needs when you first visit the website.

**Why is this needed?** The database file is ignored by git (for security), so it doesn't get cloned with the project. You need to create it manually.

### Step 4: Build Your Styles

```bash
# Build the CSS files
npm run build

# Copy Alpine.js (makes your site interactive)
cp node_modules/alpinejs/dist/cdn.min.js public/js/alpine.min.js
```

**What just happened?** You built the stylesheets that make your website look nice, and copied the JavaScript library that makes buttons and forms interactive.

### Step 5: Create Your First Admin User

```bash
# Create an admin account
php cli/create-admin.php --email=you@example.com --password=YourPassword123
```

**What just happened?** You created the first user account - this is YOUR admin account that lets you log in and manage everything.

**Important:**
- Replace `you@example.com` with your actual email
- Replace `YourPassword123` with a strong password
- Remember these credentials - you'll need them to log in!

### Step 6: Start Your Website

If you're using Laravel Herd, your site is already running! Just open your browser and go to:

```
http://my-awesome-project.test
```

**What you should see:** A welcome page showing which modules are active.

---

## Understanding Modules

Modules are like add-ons or plugins - they add specific features to your website. Caro comes with three built-in modules:

### 1. Auth Module (User Login System)

**What it does:**
- Lets users create accounts and log in
- Protects pages so only logged-in users can see them
- Lets you manage user permissions (who can do what)
- Prevents hackers from faking form submissions (CSRF protection)

**When to use it:** Almost always! Unless you're building a completely public website with no user accounts.

**What you get:**
- Login and logout pages
- User management interface (add, edit, delete users)
- Admin and regular user roles

### 2. Email Module

**What it does:**
- Sends emails to your users (welcome emails, password resets, notifications, etc.)
- Can use Amazon SES for production (sending real emails)
- Has a "testing mode" that just logs emails instead of sending them (great for development)

**When to use it:** When you need to send emails to users.

**Examples of what you might use it for:**
- Welcome emails when someone signs up
- Password reset links
- Order confirmations
- Notifications about activity

### 3. Queue Module (Background Jobs)

**What it does:**
- Runs tasks in the background so users don't have to wait
- Handles tasks that might fail and retries them automatically
- Processes tasks one at a time in order

**When to use it:** For tasks that take time or might fail.

**Examples:**
- Sending emails (send 100 welcome emails without making users wait)
- Processing uploaded images (resize, optimize)
- Generating reports
- Importing data from files

---

## Configuring Your Project

All your project settings live in the `.env` file. Let's go through the important ones:

### Basic Settings

Open your `.env` file in a text editor and you'll see:

```env
# Basic application settings
APP_ENV=local                    # Use "local" for development, "production" for live site
APP_DEBUG=true                   # Shows detailed errors (turn OFF for production!)
APP_NAME="My App"                # Your website's name

# Database settings
DB_DRIVER=sqlite                 # Type of database (SQLite is easiest)
DB_PATH=storage/database.sqlite  # Where to store your database file
```

**What to change:**
- `APP_NAME` - Change "My App" to your actual project name
- `APP_DEBUG` - Keep `true` while building, change to `false` when launching

### Turning Modules On/Off

```env
# Module toggles (true = on, false = off)
MODULE_AUTH=true      # User login system
MODULE_EMAIL=false    # Email sending
MODULE_QUEUE=false    # Background jobs
```

**To turn on a module:** Change `false` to `true`
**To turn off a module:** Change `true` to `false`

### Email Configuration (If Using Email Module)

If you turn on `MODULE_EMAIL=true`, you can configure it two ways:

#### Option 1: Development Mode (Logs emails to a file)

Just turn on the module - if you don't add AWS credentials, emails will be logged to `storage/logs/app.log` instead of being sent. This is perfect for testing!

#### Option 2: Production Mode (Actually sends emails via Amazon SES)

```env
MODULE_EMAIL=true

# Add your Amazon SES credentials
AWS_SES_REGION=us-east-1
AWS_SES_ACCESS_KEY=your-access-key-here
AWS_SES_SECRET_KEY=your-secret-key-here
AWS_SES_FROM_ADDRESS=noreply@yourdomain.com
```

**How to get AWS credentials:**
1. Sign up for [Amazon Web Services](https://aws.amazon.com/)
2. Go to Amazon SES
3. Create credentials (Access Key ID and Secret Access Key)
4. Paste them into your `.env` file

**Note:** Amazon SES starts in "sandbox mode" - you'll need to verify email addresses and request production access. See [AWS SES documentation](https://docs.aws.amazon.com/ses/) for details.

---

## Building Your First Feature

Now that everything is set up, let's add a simple feature to understand how everything works.

### Understanding the Structure

Your project is organized into folders:

```
my-awesome-project/
├── src/                    # Your application code
│   ├── Modules/           # Feature modules (Auth, Email, Queue)
│   │   ├── Auth/         # Everything related to user login
│   │   ├── Email/        # Email sending code
│   │   └── Queue/        # Background job code
│   ├── Views/            # HTML templates (what users see)
│   └── Http/
│       └── Controllers/  # Code that handles user requests
├── public/               # Publicly accessible files
│   ├── index.php        # Main entry point
│   ├── css/             # Stylesheets
│   └── js/              # JavaScript files
├── storage/             # Data storage
│   ├── database.sqlite  # Your database
│   └── logs/            # Error logs
└── tests/               # Automated tests
```

### Example: Adding a Contact Page

Let's add a simple contact page to your website.

**Step 1: Create the page template**

Create a new file: `src/Views/contact.twig`

```twig
{% extends 'layouts/app.twig' %}

{% block title %}Contact Us{% endblock %}

{% block content %}
<div class="max-w-2xl mx-auto">
    <h1 class="text-3xl font-bold mb-6">Contact Us</h1>

    <form method="POST" action="/contact">
        <div class="mb-4">
            <label class="block mb-2">Your Name</label>
            <input type="text" name="name" required
                   class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-4">
            <label class="block mb-2">Your Email</label>
            <input type="email" name="email" required
                   class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-4">
            <label class="block mb-2">Message</label>
            <textarea name="message" rows="5" required
                      class="w-full border rounded px-3 py-2"></textarea>
        </div>

        <button type="submit"
                class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
            Send Message
        </button>
    </form>
</div>
{% endblock %}
```

**What this does:** Creates a contact form with fields for name, email, and message.

**Step 2:** You would then create a Controller to handle this form, add a route, and process the submission. (See CLAUDE.md for technical details on how to do this)

---

## Common Tasks

### Adding New Users

**Via Command Line:**
```bash
php cli/create-admin.php --email=newuser@example.com --password=Password123
```

**Via Web Interface:**
1. Log in as an admin
2. Click "Users" in the navigation
3. Click "Create New User"
4. Fill in the form and choose their role (admin or user)

### Viewing Logs

If something goes wrong, check the logs:

```bash
# View recent errors
tail -f storage/logs/app.log
```

Press `Ctrl+C` to stop viewing.

### Running Background Jobs

If you've enabled the Queue module and have jobs to process:

```bash
# Start the worker (processes jobs in the background)
php cli/worker.php --queue=default --sleep=3
```

This will keep running until you stop it with `Ctrl+C`.

**What it does:** Continuously checks for new jobs and processes them one at a time.

### Rebuilding Styles

If you change any CSS files:

```bash
# One-time rebuild
npm run build

# Or watch for changes (rebuilds automatically)
npm run dev
```

### Checking Code Quality

Before committing your changes, or if you want to check your code manually:

```bash
# Run all quality checks (tests, style, architecture)
composer quality

# Auto-fix code style issues
composer cs-fix

# Run only tests
composer test
```

**Note:** If you've installed the git hooks (with `composer hooks:install`), these checks run automatically when you try to commit. If they fail, your commit will be blocked until you fix the issues.

---

## Troubleshooting

### "Permission Denied" Errors

**Problem:** Can't write to storage folders
**Solution:**
```bash
# On Mac/Linux
chmod -R 775 storage

# Make sure storage/database.sqlite can be written to
chmod 664 storage/database.sqlite
```

### "Class Not Found" Errors

**Problem:** PHP can't find your code
**Solution:**
```bash
# Rebuild the autoloader
composer dump-autoload
```

### Database Errors

**Problem:** "Table not found" or similar errors
**Solution:** Run migrations by just visiting your website - they run automatically on the first request.

### Styles Not Showing Up

**Problem:** Website looks plain with no styling
**Solution:**
```bash
# Rebuild CSS
npm run build

# Make sure the CSS file exists
ls -la public/css/app.css
```

### Can't Log In

**Problem:** Forgot your password
**Solution:**
```bash
# Create a new admin account
php cli/create-admin.php --email=newadmin@example.com --password=NewPassword123
```

### Git Commit Blocked by Quality Checks

**Problem:** When you try to commit code, you get an error saying "Quality checks failed"
**What it means:** Your code has style issues, failing tests, or architectural violations

**Solution:**
```bash
# See what the problems are
composer quality

# Auto-fix code style issues
composer cs-fix

# If tests are failing, fix the code causing the failure

# Try committing again
git commit -m "Your commit message"
```

**To temporarily skip the quality checks** (not recommended, but sometimes necessary):
```bash
git commit --no-verify -m "Your commit message"
```

---

## Next Steps

Once you're comfortable with the basics:

1. **Read CLAUDE.md** - More technical documentation about how to extend the framework
2. **Check out the test files** in `tests/` - See examples of how features work
3. **Explore the modules** in `src/Modules/` - Learn from existing code
4. **Build your first custom module** - Add your own features

---

## Getting Help

- **Documentation:** Check README.md, CLAUDE.md, and GETTINGSTARTED.md
- **Code Examples:** Look at existing modules in `src/Modules/`
- **Health Check:** Run `php cli/doctor.php` to verify your setup

---

## Remember

- **Keep APP_DEBUG=false in production** - Never show detailed errors to users
- **Back up your database** regularly (copy `storage/database.sqlite`)
- **Use strong passwords** for all accounts
- **Test changes locally** before deploying to production
- **Run `composer quality`** before committing code to catch errors early

---

**Happy Building!** 🚀

You now have everything you need to start building your own web application. Take it one step at a time, don't be afraid to experiment, and remember - everyone was a beginner once!
