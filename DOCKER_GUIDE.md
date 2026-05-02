# WebMonitor Deployment & Docker Guide

This guide will walk you through pushing your code to GitHub, creating a Docker image, and running the application on any device.

## 1. Pushing to GitHub

First, make sure you have created a **new repository** on GitHub.

```powershell
# Initialize git
git init

# Add all files (respects .gitignore)
git add .

# Commit changes
git commit -m "feat: Dockerize application and add SSL monitoring features"

# Add your GitHub remote
# REPLACE <your-github-url> with your actual repo link (e.g., https://github.com/username/web-monitor.git)
git remote add origin <your-github-url>

# Push to GitHub
git branch -M main
git push -u origin main
```

---

## 2. Creating & Pushing Docker Image (Optional)

If you want to "pull" the image directly instead of the code, you can use Docker Hub.

1.  **Login to Docker Hub**:
    ```bash
    docker login
    ```
2.  **Build and Tag the image**:
    ```bash
    # Replace 'yourusername' with your actual Docker Hub username
    docker build -t yourusername/web-monitor:latest .
    ```
3.  **Push the image**:
    ```bash
    docker push yourusername/web-monitor:latest
    ```

---

## 3. Running on Docker (The Easy Way)

On your target device, follow these steps:

### Step 1: Get the files
If you pushed the code to GitHub, simply clone it:
```bash
git clone <your-github-url>
cd web-monitor
```

### Step 2: Set up Environment
Copy the example environment file and update it:
```bash
cp .env.example .env
```
> [!IMPORTANT]
> Edit the `.env` file and set your `APP_KEY`, `DB_PASSWORD`, and any AI/Mail credentials.

### Step 3: Launch with Docker Compose
Run the following command to build and start everything:
```bash
docker-compose up -d --build
```

### Step 4: Initialize the App
Once the containers are running, run the initialization script to set up the database and cache:
```bash
docker-compose exec app bash docker-init.sh
```

---

## 4. Useful Docker Commands

- **Stop the app**: `docker-compose down`
- **View logs**: `docker-compose logs -f app`
- **Recompile assets (CSS/JS)**: `docker-compose exec app npm run build`
- **Clear application cache**: `docker-compose exec app php artisan optimize:clear`

## 5. Troubleshooting
- **Database Connection**: The `docker-compose.yml` is set up to use `db` as the hostname. Ensure your `.env` has `DB_HOST=db`.
- **Permissions**: If you get a "permission denied" error on storage, run:
  `docker-compose exec app chown -R www-data:www-data storage bootstrap/cache`
