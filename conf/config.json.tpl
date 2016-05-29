{
    "server": {
        "host": "localhost",
        "port": 8080
    },
    "cache": {
        "server": "127.0.0.1",
        "port": 11211
    },
    "monitor": {
        "motion": {
            "pin": 4,
            "calibrate": 0
        },
        "led": {
            "pin": 11,
            "light": 2
        },
        "camera": {

        }
    },
    "mirror": {
        "timezone": "America/Montreal",
        "dimafter": 20
    },
    "interact": {
        "useragent": "kaecyra/alice-server",
        "weather": {
            "location": {
                "city": "CITY",
                "latitude": LATITUDE,
                "longitude": LONGITUDE,
                "units": "ca"
            },
            "source": "forecast",
            "sources": {
                "forecast": {
                    "host": "https://api.forecast.io",
                    "path": "forecast/{api}/{latitude},{longitude}",
                    "key": ""
                }
            }
        },
        "news": {
            "source": "reddit",
            "sources": {
                "nyt": {
                    "host": "http://api.nytimes.com",
                    "path": "svc/news/v3/content/all.json",
                    "key": "",
                    "arguments": {
                        "limit": 20,
                        "offset": 0,
                        "time-period": 24
                    }
                },
                "reddit": {
                    "host": "http://www.reddit.com",
                    "path": "r/worldnews.json"
                }
            }
        }
    }
}