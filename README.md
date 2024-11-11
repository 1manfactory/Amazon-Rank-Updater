# Amazon Rank Updater

This project is designed to fetch product rankings and titles from the Amazon API based on specified ASINs (Amazon Standard Identification Numbers) and update a local database with the latest rankings and product titles. The script runs continuously, making it suitable for use as a Linux service. This setup is particularly useful for monitoring ranking changes and acting on any discrepancies or errors promptly.

## How It Works

The `AmazonRankUpdater` class communicates with the Amazon API to retrieve product rankings and titles, updating this information in a database. The script can run in either **live mode** (making actual API calls) or **debug mode** (using mock data for testing purposes).

## Features

- Fetches ASINs from a local database
- Retrieves sales rank and title data from Amazon API
- Updates the database with the latest rank and title information
- Includes debug mode and verbose logging
- Runs as a continuous process, updating ranks daily
- Sends email notifications for critical errors

## Database Structure

The `products` table serves as the target table and is **created by the program** for ranking updates, and the `source` table should contain the ASINs to track.

### `products` Table Structure

| Column Name | Data Type | Description                         |
|-------------|-----------|-------------------------------------|
| `id`        | INT       | Auto-incremented primary key       |
| `asin`      | VARCHAR   | The unique ASIN for the product    |
| `title`     | VARCHAR   | The product title                  |
| `date`      | DATE      | Date of the ranking update         |
| `rank`      | INT       | The latest product rank            |

- **`asin`**: Stores the ASIN of the product, which serves as a unique identifier.
- **`title`**: Stores the title of the product as retrieved from the Amazon API.
- **`date`**: The date of the ranking update.
- **`rank`**: The latest ranking of the product as provided by the Amazon API.

This table is used as the target table where the rankings and titles are stored. Each ASIN is unique for a given date to ensure that daily rankings are tracked separately.

## Requirements

- PHP 7.0 or higher
- MySQL database
- Amazon Product Advertising API credentials

## Installation

1. Clone this repository:
   ```
   cd ~
   git clone https://github.com/1manfactory/amazon-rank-updater.git
   ```

2. Install:
   ```
   cd amazon-rank-updater
   chmod +x php-wrapper.sh
   mkdir -p ~/bin
   ln -s ~/projects/amazon-rank-updater/php-wrapper.sh ~/bin/amazon-rank-updater
   export PATH="$HOME/bin:$PATH"
   composer install
   ```

3. Set up your database and update the `config.php` file with your credentials.
   ```
   cp config.example.php config.php
   nano config.php
   ```

## Usage

Run the script using the provided wrapper:

```
./php-wrapper.sh [options]
```

Options:
- `-h, --help`: Display help information
- `-v, --verbose`: Increase verbosity
- `-l, --live`: Run in live mode (perform actual API calls)
- `-d, --debug`: Insert mock data into the database

## Configuration

Edit the `config.php` file to set:
- Database connection details
- Amazon API credentials
- Error notification email address

## Setting Up as a Linux Service

To run this script continuously, you can set it up as a Linux service. Here’s a basic example of a service file (`/etc/systemd/system/amazon-rank-updater.service`):

```ini
[Unit]
Description=Amazon Rank Updater Service
After=network.target

[Service]
ExecStart=/path/to/amazon-rank-updater/php-wrapper.sh 
Restart=always
User=youruser
Group=yourgroup

[Install]
WantedBy=multi-user.target
```

Place this file in /etc/systemd/system/

Enable and start the service:

```bash
sudo systemctl enable amazon-rank-updater.service
sudo systemctl start amazon-rank-updater.service
```

## Email Notifications for Error Monitoring

Since this script runs indefinitely, it’s beneficial to set up email notifications for error alerts. This way, you’ll receive timely notifications in case of failures, allowing for prompt troubleshooting. Configure your server or email system to send error logs from this script to your email.

## Resources

https://github.com/thewirecutter/paapi5-php-sdk

https://partnernet.amazon.de/assoc_credentials/home

https://webservices.amazon.com/paapi5/documentation/

https://github.com/thewirecutter/paapi5-php-sdk/tree/main/src/com/amazon/paapi5/v1

https://webservices.amazon.com/paapi5/documentation/quick-start/using-curl.html

https://webservices.amazon.com/paapi5/documentation/quick-start/using-sdk.html

https://us-east-1.console.aws.amazon.com/iam/home#/home -> NAVIGATION: Users -> Create User -> Security Information -> Access keys -> Generate Access Key

https://partnernet.amazon.de/signup

Then sign up for Product Advertising API at https://affiliate-program.amazon.com/assoc_credentials/home

https://docs.aws.amazon.com/IAM/latest/UserGuide/id_credentials_access-keys.html

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.
