# FroshAMQP

[![Join the chat at https://gitter.im/FriendsOfShopware/Lobby](https://badges.gitter.im/FriendsOfShopware/Lobby.svg)](https://gitter.im/FriendsOfShopware/Lobby)

Use AMQP Queue for Elastic Search Backlog Syncing.

## Requirements

- Shopware 5.5
- AMQP Server (like RabbitMQ)

## Installation

- Download latest release
- Extract the zip file in `shopware_folder/custom/plugins/`

### Configure AMQP Host in ``config.php``

```php
'amqp' => [
    'host' => 'rabbitmq',
    'username' => 'foo',
    'password' => 'bar',
    'port' => '5672'
]
```

## Usage

After the activation of the Plugin all backlogs will be send to the AMQP Server. You can start the worker with `./bin/console frosh:es:backend:worker` and `frosh:es:worker`.

**You should consider creating systemd services for these two workers**


## Implement more Queue's?

Publish the message using the `SimpleMessagePublisher`, create a new Command and extend it from `AbstractWorkerCommand`

## Contributing

Feel free to fork and send pull requests!


## Licence

This project uses the [MIT License](LICENCE.md).
