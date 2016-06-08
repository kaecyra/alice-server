{
    "server": {
        "mode": "ws",
        "host": "localhost",
        "port": 8080,
        "address": "127.0.0.1",
        "retry": {
            "delay": 15
        }
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
            },
            {
                "type": "dates",
                "source": "local",
                "configuration": {
                    "filters": {
                        "special": {
                            "source": "file",
                            "file": "data/data-local-special.json"
                        }
                    }
                },
                "satisfies": [
                    "special"
                ]
            }
        ]
    },
    "devices": [
        {
            "type": "mirror",
            "id": "lrmirror01",
            "auth": "",
            "name": "Livingroom Mirror",
            "server": {
                "path": "/mirror"
            },
            "settings": {
                "location": {
                    "city": "Montreal",
                    "state": "QC",
                    "country": "Canada",
                    "timezone": "America/Montreal",
                    "locale": "en_US",
                    "latitude": 45.473509,
                    "longitude": -73.581727,
                    "units": "metric"
                },
                "dim": 20
            },
            "interface": {
                "sources": [
                    {
                        "type": "weather",
                        "filter": "geo"
                    },
                    {
                        "type": "news",
                        "filter": "worldnews",
                        "config": {
                            "limit": 5,
                            "width": 70
                        }
                    },
                    {
                        "type": "dates",
                        "filter": "special"
                    }
                ],
                "sensors": [
                    {
                        "type": "motion",
                        "id": "lrmirror01-motion"
                    }
                ]
            }
        },
        {
            "type": "motion",
            "id": "lrmirror01-motion",
            "auth": "sensorAUTH",
            "name": "Livingroom Mirror",
            "server": {
                "path": "/sensor"
            }
        }
    ]
}