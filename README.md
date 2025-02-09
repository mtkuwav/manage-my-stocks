# PHP Docker Project - A Fully Containerized PHP Development Environment

## Introduction

Welcome to the PHP Docker Project! This project is designed to make your life easier by providing a fully containerized PHP development environment. With Docker, you can run your PHP application, MySQL database, and phpMyAdmin with just a few commands. No more "it works on my machine" excuses!

## Prerequisites

Before you can start, you'll need to have Docker and Docker Compose installed on your machine. Here's how to do it:

- For macOS and Windows users, download and install [Docker Desktop](https://docs.docker.com/get-started/introduction/get-docker-desktop/).
- For Linux users, follow the instructions in the [Docker documentation](https://docs.docker.com/desktop/setup/install/linux/).
- Composer installed on your machine. You can download it from [here](https://getcomposer.org/download/).

## Configuration

Before you start the project, you'll need to configure your environment variables. Copy the [.env.sample](./.env.sample) file to `.env` and fill in the required values:

```sh
cp .env.sample .env
```

Edit the `.env` file and set the following variables:

- `DB_NAME`: The name of your MySQL database
- `DB_USER`: The MySQL user
- `DB_PASSWORD`: The MySQL user's password
- `DB_ROOT_PASSWORD`: The MySQL root user's password
- `DB_PORT`: The port for MySQL (default is 3306)
- `PHPMYADMIN_PORT`: The port for phpMyAdmin (default is 8090)

## How to Use?

```sh
cd app && composer install && cd ../
```

Once you have Docker and Docker Compose installed and your environment variables configured, you can start the project with the following command:

```sh
docker-compose up -d
```

**Explanation:**

- `docker-compose`: The Docker Compose command
- `up`: The command to start the services
- `-d`: Run the services in detached mode (in the background)

This command will start the Nginx server, PHP application, MySQL database, and phpMyAdmin.

_That's it! You now have a fully containerized PHP development environment. Happy coding!_

## Accessing the Services

- Your PHP application will be available at [http://localhost](http://localhost)
- phpMyAdmin will be available at [http://localhost:8090](http://localhost:8090).

## Stopping the Project

To stop the project, run the following command:

```sh
docker-compose down
```

## Logs

To view the logs of the services, you can use the following command:

```sh
docker-compose logs -f
```

This will show the logs of all the services. Press `Ctrl + C` to exit the logs.

## License

This project is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Conclusion

And there you have it! A fully containerized PHP development environment with Docker. Now you can focus on writing awesome PHP code without worrying about the underlying infrastructure. Happy coding!

P.S. If you encounter any issues, remember: "It's not a bug, it's a feature!"
