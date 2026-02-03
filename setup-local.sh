#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Bingo System - Local Setup Script${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Function to print steps
step() {
    echo -e "${YELLOW}→ $1${NC}"
}

# Function to print success
success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Function to print error
error() {
    echo -e "${RED}✗ $1${NC}"
    exit 1
}

# Check if Docker is installed
step "Checking Docker installation..."
if ! command -v docker &> /dev/null; then
    error "Docker is not installed"
fi
success "Docker is installed"

# Check if Docker Compose is installed
step "Checking Docker Compose installation..."
if ! command -v docker-compose &> /dev/null; then
    error "Docker Compose is not installed"
fi
success "Docker Compose is installed"

echo ""
step "Creating .env files from examples..."

# Copy .env files
if [ ! -f backend-laravel/.env ]; then
    cp backend-laravel/.env.example backend-laravel/.env
    success "Created backend-laravel/.env"
else
    echo -e "${YELLOW}  (backend-laravel/.env already exists)${NC}"
fi

if [ ! -f generator-python/.env ]; then
    cp generator-python/.env.example generator-python/.env
    success "Created generator-python/.env"
else
    echo -e "${YELLOW}  (generator-python/.env already exists)${NC}"
fi

echo ""
step "Building and starting Docker containers..."
docker-compose up -d --build

if [ $? -ne 0 ]; then
    error "Failed to start Docker containers"
fi

success "Docker containers are starting..."

echo ""
step "Waiting for services to be healthy (30 seconds)..."
sleep 30

# Check if containers are running
echo ""
step "Checking container status..."
docker-compose ps

echo ""
step "Running database migrations..."
docker-compose exec -T laravel php artisan migrate --force

if [ $? -ne 0 ]; then
    error "Failed to run migrations"
fi
success "Database migrations completed"

echo ""
step "Creating admin user..."

# Create admin user via tinker
docker-compose exec -T laravel php artisan tinker <<EOF
use App\Models\User;
use Illuminate\Support\Str;

\$adminExists = User::where('email', 'admin@example.com')->exists();

if (!\$adminExists) {
    User::create([
        'id' => Str::uuid(),
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password123'),
        'role' => 'admin'
    ]);
    echo "Admin user created!\\n";
} else {
    echo "Admin user already exists\\n";
}
EOF

success "Admin user setup completed"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  ✓ Setup Completed Successfully!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

echo -e "${BLUE}Quick Start Commands:${NC}"
echo ""
echo -e "${YELLOW}1. Test API Health:${NC}"
echo "   curl http://localhost:8000/api/health"
echo ""
echo -e "${YELLOW}2. Login and Get Token:${NC}"
echo "   curl -X POST http://localhost:8000/api/auth/login \\"
echo "     -H \"Content-Type: application/json\" \\"
echo "     -d '{\"email\":\"admin@example.com\",\"password\":\"password123\"}'"
echo ""
echo -e "${YELLOW}3. Create an Event:${NC}"
echo "   See GUIA-SETUP-LOCAL.md for detailed instructions"
echo ""
echo -e "${YELLOW}4. View Logs:${NC}"
echo "   docker-compose logs -f laravel    # Laravel logs"
echo "   docker-compose logs -f generator  # Python logs"
echo ""
echo -e "${YELLOW}5. Stop Services:${NC}"
echo "   docker-compose down"
echo ""

echo -e "${BLUE}Useful Ports:${NC}"
echo "  • Laravel API:  http://localhost:8000"
echo "  • Python API:   http://localhost:8001"
echo "  • MySQL:        localhost:3306"
echo "  • Redis:        localhost:6379"
echo ""

echo -e "${BLUE}Database Credentials:${NC}"
echo "  • User: bingo_user"
echo "  • Password: bingo_password"
echo "  • Database: bingo_db"
echo ""

echo -e "${BLUE}Admin Credentials:${NC}"
echo "  • Email: admin@example.com"
echo "  • Password: password123"
echo ""
