
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

var React = require('react');
var ReactDOM = require('react-dom');

var Mirror = require('./controllers/mirror.js');
var SocketClient = require('./common/socketclient.js');
var Events = require('./common/events.js');

jQuery(document).ready(function() {
    new Mirror(device, new SocketClient(server), new Events());
});