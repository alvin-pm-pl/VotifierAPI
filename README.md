# VotifierAPI
An API plugin which provides Vote API for votifier.

# Why?
PHP sucks. No other reasons. Kotlin W.

# Setup
1. Download the latest release from [here](https://github.com/alvin0319/VotifierAPI/releases/latest).
2. Run server and stop it.
3. Edit `config.yml` as you need. (This need to be same as [votifier-server](https://github.com/alvin0319/votifier-server)'s config.json)
4. Run server again.

# API
### alvin0319\VotifierAPI\event\PlayerVoteEvent
* `getAddress() : string` - Returns the address of the voter.
* `getUsername() : string` - Returns the username of the voter.
* `getServiceName() : string` - Returns the service name of the voter.
* `getTimestamp() : string` - Returns the timestamp of the vote.

# License
This project is licensed under the MIT License. See [LICENSE](./LICENSE) for more details.
