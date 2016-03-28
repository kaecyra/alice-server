# alice-http
Smart Mirror WebSocket server.

This repository contains two daemons: a WebSocket server and a hardware (and API)
monitoring daemon. They are both required.

## server

The server acts as a message broker between the hardware monitor and the UI layer,
and provides client registration and management.

## monitor

The monitor connects to the server as a client and monitors the mirror hardware 
for updates, as well as polling the remote API data sources on a regular basis 
to provide fresh data for the UI.