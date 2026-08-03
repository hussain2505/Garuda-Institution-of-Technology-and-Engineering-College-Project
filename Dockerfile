FROM php:8.2-apache

# Install PDO MySQL extension for your database connections
RUN docker-php-ext-install pdo pdo_mysql

# Copy your frontend and backend code into Apache's public folder
COPY . /var/www/html/

# Expose the web port
EXPOSE 80
