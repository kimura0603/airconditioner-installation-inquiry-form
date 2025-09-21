#!/bin/bash

# Air Conditioner Reservation System Test Runner

echo "=== Air Conditioner Reservation System Tests ==="
echo ""

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if Docker is running
if ! docker info >/dev/null 2>&1; then
    echo -e "${RED}Error: Docker is not running${NC}"
    exit 1
fi

# Check if containers are running
echo -e "${BLUE}Checking Docker containers...${NC}"
if ! docker-compose ps | grep -q "Up"; then
    echo -e "${YELLOW}Starting Docker containers...${NC}"
    docker-compose up -d
    sleep 5
fi

# Install dependencies if needed
if [ ! -d "vendor" ]; then
    echo -e "${BLUE}Installing Composer dependencies...${NC}"
    docker run --rm -v $(pwd):/app composer install
fi

# Create test database if it doesn't exist
echo -e "${BLUE}Setting up test database...${NC}"
docker exec air-conditionner-db-1 mysql -u root -ppassword -e "CREATE DATABASE IF NOT EXISTS air_conditioner_db_test;" 2>/dev/null

# Run database migrations for test database
echo -e "${BLUE}Setting up test database schema...${NC}"
docker exec air-conditionner-db-1 mysql -u root -ppassword air_conditioner_db_test < sql/init.sql 2>/dev/null
if [ -f "sql/update_schema.sql" ]; then
    docker exec air-conditionner-db-1 mysql -u root -ppassword air_conditioner_db_test < sql/update_schema.sql 2>/dev/null
fi

# Run PHPUnit tests
echo -e "${BLUE}Running PHPUnit tests...${NC}"
echo ""

# Run unit tests
echo -e "${YELLOW}=== Unit Tests ===${NC}"
docker run --rm \
    --network air-conditionner_default \
    -v $(pwd):/app \
    -w /app \
    -e DB_HOST=air-conditionner-db-1 \
    -e DB_NAME=air_conditioner_db_test \
    -e DB_USER=root \
    -e DB_PASSWORD=password \
    php:8.1-cli \
    ./vendor/bin/phpunit --testdox --colors=always tests/Unit/

echo ""

# Run integration tests
echo -e "${YELLOW}=== Integration Tests ===${NC}"
docker run --rm \
    --network air-conditionner_default \
    -v $(pwd):/app \
    -w /app \
    -e DB_HOST=air-conditionner-db-1 \
    -e DB_NAME=air_conditioner_db_test \
    -e DB_USER=root \
    -e DB_PASSWORD=password \
    php:8.1-cli \
    ./vendor/bin/phpunit --testdox --colors=always tests/Integration/

echo ""

# Run all tests with coverage (if requested)
if [ "$1" = "--coverage" ]; then
    echo -e "${YELLOW}=== Test Coverage Report ===${NC}"
    docker run --rm \
        --network air-conditionner_default \
        -v $(pwd):/app \
        -w /app \
        -e DB_HOST=air-conditionner-db-1 \
        -e DB_NAME=air_conditioner_db_test \
        -e DB_USER=root \
        -e DB_PASSWORD=password \
        php:8.1-cli \
        ./vendor/bin/phpunit --coverage-text --colors=always
fi

# Clean up test database
echo -e "${BLUE}Cleaning up test database...${NC}"
docker exec air-conditionner-db-1 mysql -u root -ppassword -e "DROP DATABASE IF EXISTS air_conditioner_db_test;" 2>/dev/null

echo ""
echo -e "${GREEN}Tests completed!${NC}"
echo ""
echo "Usage:"
echo "  ./run-tests.sh           - Run all tests"
echo "  ./run-tests.sh --coverage - Run tests with coverage report"
echo ""
echo "Individual test commands:"
echo "  ./vendor/bin/phpunit tests/Unit/ReservationConfirmationTest.php"
echo "  ./vendor/bin/phpunit tests/Integration/ApiTest.php"
echo "  ./vendor/bin/phpunit tests/Integration/ReservationWorkflowTest.php"