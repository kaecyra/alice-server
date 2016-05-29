# ALICE Smart Home Server

This repository contains the ALICE Smart Home server. This server is the core of the ALICE system and is required for all installations. 

All devices and sensors connect to the server in order to facilitate interconnection. The server is responsible for remote API data aggregation and for pushing updates to devices.

## Message Broker

The server acts as a message broker between all connected devices.

## Sensors

Sensors connect to the server as clients and push updates to the server when their environment changes.