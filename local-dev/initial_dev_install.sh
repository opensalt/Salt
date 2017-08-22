#!/bin/bash

# Get to main project directory
cd $(dirname $0)/..

ln -sf .env.dist docker/.env
ln -sf docker/.env ./.env
ln -sf docker-compose.dev.yml docker/docker-compose.yml


# Install php libraries
./bin/composer --no-interaction install

# Replace tokens with random values
TOKEN1=$(openssl rand -base64 32)
TOKEN2=$(openssl rand -base64 32)
sed -i '' "s#ThisTokenIsNotSoSecretSoChangeIt#${TOKEN1}#" app/config/parameters.yml
sed -i '' "s#ThisTokenIsNotSoSecretChangeIt#${TOKEN2}#" app/config/parameters.yml

# Set secure_cookie to false to allow http connections
sed -i '' "s#secure_cookie:.*#secure_cookie: false#" app/config/parameters.yml

# Run gulp to create css and js files
./bin/gulp

# Start docker containers
cd docker
docker-compose up -d
cd ..

# Do database migrations
./bin/console doctrine:migrations:migrate --no-interaction

# Make bundle assets available
./bin/console assets:install web --symlink --relative

# Add an initial super user
./bin/console salt:user:add admin Unknown --password=secret --role=super-user

echo 'You should now be able to connect to http://127.0.0.1:3000'
