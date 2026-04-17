FROM mcr.microsoft.com/playwright/python:v1.40.0-jammy

# Install PHP for API integration
RUN apt-get update && apt-get install -y \
    php8.1-cli \
    php8.1-curl \
    php8.1-json \
    python3-pip \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy requirements
COPY requirements.txt .

# Install Python dependencies
RUN pip3 install --no-cache-dir -r requirements.txt

# Install Playwright browsers
RUN playwright install chromium

# Keep container running
CMD ["tail", "-f", "/dev/null"]
