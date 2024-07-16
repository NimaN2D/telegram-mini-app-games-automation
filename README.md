# Hamster Kombat Game Automation

This Laravel 11 project automates the Hamster Kombat Game by handling taps and purchasing upgrades based on the configured strategies.

## Setup Instructions

### Prerequisites

- Docker & Docker Compose
- Laravel Sail
- PHP 8.1 or higher
- Composer

### Installation

1. **Clone the Repository:**
    ```bash
    git clone https://github.com/your-repo/hamster-kombat-game.git
    cd hamster-kombat-game
    ```

2. **Install Dependencies:**
    ```bash
    composer install
    ```

3. **Environment Configuration:**
   Copy the `.env.example` to `.env` and update the environment variables.
    ```bash
    cp .env.example .env
    ```

4. **Set Environment Variables:**
    ```dotenv
    HAMSTER_INIT_DATA_RAW=your_init_data_raw_here
    HAMSTER_FINGERPRINT=your_fingerprint_json_here
    HAMSTER_SPEND_PERCENTAGE=0.20
    HAMSTER_MIN_BALANCE=100000000
    ```

5. **Docker Setup:**
    ```bash
    ./vendor/bin/sail up
    ./vendor/bin/sail artisan migrate
    ```

### Usage

#### Running the Command

You can run the command manually to play the game:

```bash
php artisan play:hamster
