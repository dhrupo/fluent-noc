#!/bin/bash

# Setup script for Fluent NOC Manager
# This script installs Composer and npm dependencies

echo "Installing Composer dependencies..."
composer install --no-interaction --prefer-dist

echo ""
echo "Installing npm dependencies..."
npm install

echo ""
echo "Setup complete!"
echo ""
echo "If you encounter SSL certificate errors with Composer, try:"
echo "  composer config -g secure-http false"
echo ""
echo "If you encounter npm permission errors, try:"
echo "  sudo chown -R \$(whoami) ~/.npm"
echo "  npm cache clean --force"

