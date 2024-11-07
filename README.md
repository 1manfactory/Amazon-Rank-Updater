# Amazon Rank Updater

Update Amazon product ranks for a list of ASINs stored in a database. It uses the Amazon Product Advertising API to fetch the latest sales rank for each product.

## Features

- Fetches ASINs from a local database
- Retrieves sales rank data from Amazon API
- Updates the database with the latest rank information
- Includes debug mode and verbose logging
- Runs as a continuous process, updating ranks daily
- Sends email notifications for critical errors

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
   composer require thewirecutter/paapi5-php-sdk
   ```

3. Set up your database and update the `config.php` file with your credentials.
   ```
   cp config.example.php config.php
   nano config.php
   ```

4. Update `config.php` with your Amazon API credentials and email settings.

## Usage

Run the script using the provided wrapper:

```
amazon_rank_updater [options]
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
- API request intervals and daily update frequency
- Error notification email address

## Resources

https://github.com/thewirecutter/paapi5-php-sdk

https://partnernet.amazon.de/assoc_credentials/home

https://webservices.amazon.com/paapi5/documentation/

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
