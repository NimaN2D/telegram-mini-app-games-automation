# Telegram Mini App Games Automation

This Laravel 11 project automates the Hamster Kombat and Musk Empire Games by handling taps and purchasing upgrades (improvements) based on the configured strategies.

## Setup Instructions

### Prerequisites

- Docker & Docker Compose

### Installation

1. **Clone the Repository:**
    ```bash
    git clone https://github.com/NimaN2D/telegram-mini-app-games-automation.git
    cd telegram-mini-app-games-automation
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
    # Hamster configuration
    HAMSTER_INIT_DATA_RAW='INIT_DATA_RAW'
    HAMSTER_FINGERPRINT='FINGERPRINT'
    HAMSTER_SPEND_PERCENTAGE=100
    HAMSTER_MIN_BALANCE=0
    
    # Musk Empire configuration
    MUSK_EMPIRE_INIT_DATA='INIT_DATA'
    MUSK_EMPIRE_SPEND_PERCENTAGE=100
    MUSK_EMPIRE_MIN_BALANCE=0
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

   To obtain the `MUSK_EMPIRE_INIT_DATA` variable, follow these steps:

    1. Open the Telegram web version in the Chrome browser on your desktop by navigating to [https://web.telegram.org/k](https://web.telegram.org/k) and log into your Telegram account.
    2. Enter the Musk Empire bot by clicking on the link [@muskempire_bot](https://t.me/muskempire_bot).
    3. Click on the "play" button to load the game. You will see a message indicating that the game can only be played on mobile.
    4. Press `F12` on your keyboard to open the Chrome Developer Tools. Clear the Console by clicking on the icon that looks like a no-entry sign.
    5. Copy and paste the following code into the Console and press Enter:
        ```javascript
        const iframe = document.getElementsByTagName('iframe')[0];
        iframe.src = iframe.src.replace(/(tgWebAppPlatform=)[^&]+/, "$1android");
        ```
    6. After entering the code, you will be provided with a link. Copy this link and open it directly in your browser's address bar.
    7. Tap one time in the game and then open the Developer Tools (`F12`) again. Go to the Network tab and look for a request named `auth`.
    8. From the payload of this request, copy the `initData` variable and set it in your `.env` file.


5. **Docker Setup:**
    ```bash
    docker-compose up -d --build
    ```
   
6. **Install Dependencies:**
    ```bash
   docker-compose exec game-automation composer install
   ```

### Usage
The service will start automatically when the Docker containers are up. If you need to manually run the command to play the game, use:

```bash
docker-compose exec game-automation php artisan play:hamster
docker-compose exec game-automation php artisan play:musk-empire
```

### Contribute
Contributions are welcome! Please follow these steps to contribute:

1. Fork the repository.
2. Create a new branch (`git checkout -b feature-branch`).
3. Make your changes.
4. Commit your changes (`git commit -m 'Add some feature'`).
5. Push to the branch (`git push origin feature-branch`).
6. Create a new Pull Request.

Please make sure to update tests as appropriate.

### License
This project is licensed under the MIT License.


### Disclaimer 
This project is intended for educational and personal use only. Using this automation tool in the Hamster Kombat Game might violate the game's terms of service. Use at your own risk. The developers of this project are not responsible for any consequences, including but not limited to being banned from the game.
