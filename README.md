# RRAPP-UI

This is the webinterface for [Rapid Risk Assessment App](https://br.biodiversity.cloud).

It reads and display statistics and visualization from an ElasticSearch with data of [rrapp-idx](https://github.com/biodivdev/rrapp-idx).

## Running

### Manual 

First, start your ElasticSearch 5.0. 

Then [run the dwc-bot-es](https://github.com/biodivdev/rrapp-idx/) and [run rrapp-idx](https://github.com/biodivdev/rrapp-idx).

This will populate the database.

### Run with Docker

Run the docker container

    $ docker run -d -volume /etc/biodiv:/etc/biodiv:ro -p8080:80 diogok/rrapp-ui

### Run the JAR

Download the latest jar from the [ realases page ](https://github.com/biodivdev/rrapp-ui/releases) and run it:

    $ java -server -jar rrapp-ui.jar

### Configuration

It will look for a configuration file on /etc/biodiv/config.ini or at the file defined by CONFIG environment variable.

The configuration file looks like the following:

    ELASTICSEARCH=http://localhost:9200
    INDEX=dwc

ElasticSearch tells to which elasticsearch server to connect. INDEX tells which ElasticSearch index to use.

You can set the configuration override to use with environment variables, such as:

    $ CONFIG=/etc/biodiv/dwc.ini ELASTICSEARCH=http://localhost:9200 INDEX=dwc java -jar rrapp-ui.jar

If not running on a system with environment variables you can also set them using java properties, as such:

    $ java -jar -DLOOP=true -DCONFIG=/etc/biodiv/config.ini -DELASTICSEARCH=http://localhost:9200 -DINDEX=dwc rrapp-ui.jar

## License

MIT

