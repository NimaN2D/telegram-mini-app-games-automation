# Hamster Kombat Game Automation

This Laravel 11 project automates the Hamster Kombat Game by handling taps and purchasing upgrades based on the configured strategies.

## Setup Instructions

### Prerequisites

- Docker & Docker Compose
- Laravel Sail
- PHP 8.2 or higher
- Composer

### Installation

1. **Clone the Repository:**
    ```bash
    git clone https://github.com/NimaN2D/telegram-web-game-hamster-auto-play.git
    cd telegram-web-game-hamster-auto-play
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

   To obtain the `HAMSTER_INIT_DATA_RAW` and `HAMSTER_FINGERPRINT` variables, follow these steps:

    1. Open the Telegram web version in the Chrome browser on your desktop by navigating to [https://web.telegram.org/k](https://web.telegram.org/k) and log into your Telegram account.
    2. Enter the Hamster Kombat bot by clicking on the link [@hamster_kombat_bot](https://t.me/hamster_kombat_bot).
    3. Click on the "play" button to load the game. You will see a message indicating that the game can only be played on mobile.
    4. Press `F12` on your keyboard to open the Chrome Developer Tools. Clear the Console by clicking on the icon that looks like a no-entry sign.
    5. Copy and paste the following code into the Console and press Enter:
        ```javascript
        const iframe = document.getElementsByTagName('iframe')[0];
        iframe.src = iframe.src.replace(/(tgWebAppPlatform=)[^&]+/, "$1android");
        console.log("🐹 Hamster:", iframe.src);
        ```
    6. After entering the code, you will be provided with a link. Copy this link and open it directly in your browser's address bar.
    7. Tap one time in the game and then open the Developer Tools (`F12`) again. Go to the Network tab and look for a request named `auth-by-telegram-webapp`.
    8. From the payload of this request, copy the `initDataRaw` and `fingerprint` variables and set them in your `.env` file.

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
```

### Disclaimer 
This project is intended for educational and personal use only. Using this automation tool in the Hamster Kombat Game might violate the game's terms of service. Use at your own risk. The developers of this project are not responsible for any consequences, including but not limited to being banned from the game.
