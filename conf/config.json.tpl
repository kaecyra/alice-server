{
    "server": {
        "host": "localhost",
        "port": 8080,
        "address": "127.0.0.1"
    },
    "cache": {
        "server": "127.0.0.1",
        "port": 11211
    },
    "data": {
        "client": {
            "useragent": "kaecyra/alice-server"
        },
        "sources": [
            {
                "type": "weather",
                "source": "forecast",
                "configuration": {
                    "host": "https://api.forecast.io",
                    "key": "",
                    "filters": {
                        "geo": {
                            "path": "forecast/{api}/{latitude},{longitude}"
                        }
                    }
                },
                "satisfies": [
                    "geo"
                ]
            },
            {
                "type": "news",
                "source": "reddit",
                "configuration": {
                    "host": "http://www.reddit.com",
                    "filters": {
                        "worldnews": {
                            "path": "r/worldnews.json"
                        },
                        "localnews": {
                            "path": "r/{city}.json"
                        }
                    }
                },
                "satisfies": [
                    "worldnews",
                    "localnews"
                ]
            }
        ]
    }
}