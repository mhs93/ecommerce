module.exports = {
    apps: [
        {
            name: "laravel-backend-ecom",
            script: "artisan",
            args: "serve",
            interpreter: "php",
            instances: 1,
            autorestart: true,
            watch: false,
            max_memory_restart: "1G",
            env: {
                NODE_ENV: "production",
                APP_ENV: "production",
                PORT: 8000,
            },
        },
    ],
};
