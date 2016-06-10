
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

var React = require('react');
var ReactDOM = require('react-dom');

/**
 * WebSocket Client
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-webui
 * @param {Object} server
 */
function SocketClient(server) {
    
    /**
     * Server config
     * @type Object
     */
    this.server = server;
    
    /**
     * WebSocket instance
     * @type WebSocket
     */
    this.socket = null;
    
    /**
     * Retry delay timer ID
     * @type {Integer}
     */
    this.delayTimer = null;
    
    /**
     * Client state
     *  offline
     *  configured
     *  connecting
     *  connected
     *  retrying
     *  registered
     *  ready
     * @type {String}
     */
    this.state = 'offline';
    
    /**
     * Events
     * @type {Events}
     */
    this.events = null;
    
    /**
     * Server version
     * @type {String}
     */
    this.registeredVersion = null;
};

/**
 * Proxy a function to ourself
 * 
 * @param {Function} fn
 * @returns {undefined}
 */
SocketClient.prototype.proxy = function(fn) {
    return $.proxy(fn, this);
};

/**
 * Start socket client and prepare to connect
 * 
 * @param {Events} events
 * @returns {undefined}
 */
SocketClient.prototype.start = function(events) {
    this.events = events;
    this.setState('configured');

    setTimeout(this.proxy(this.connect), 1000);
};

/**
 * Connect to socket server
 * 
 * @returns {undefined}
 */
SocketClient.prototype.connect = function() {
    this.setState('connecting', {
        status: 'connecting'
    });
    var socketAddress = this.server.mode+'://'+this.server.host+':'+this.server.port+this.server.path;
    this.log(' server: '+socketAddress);

    // Connect to websocket server
    this.socket = new WebSocket(socketAddress);
    this.socket.onopen = this.proxy(this.onopen);
    this.socket.onmessage = this.proxy(this.onmessage);
    this.socket.onerror = this.proxy(this.onerror);
    this.socket.onclose = this.proxy(this.onclose);
};

/**
 * Retry connection
 * 
 * @returns {undefined}
 */
SocketClient.prototype.retry = function() {
    // Let everyone know that we're retrying
    this.events.fire('retry');

    this.setState('retrying', {
        status: 'waiting',
        delay: this.server.retry.delay
    });

    this.log(" retrying connection in "+this.server.retry.delay+" seconds");
    
    var delay = this.server.retry.delay * 1000;
    clearTimeout(this.delayTimer);
    this.delayTimer = setTimeout(this.proxy(this.connect), delay);
};

/**
 * Set mirror state
 * 
 * @param {String} state
 * @param {Object} data optional extra data
 * @returns {undefined}
 */
SocketClient.prototype.setState = function(state, data) {
    this.log("state: "+state);
    this.state = state;
    
    var event = {
        mode: state
    };
    if (data) {
        jQuery.extend(event, data);
    }
    this.events.fire('socketstate', event);
};

/**
 * Event handler: socket opened
 * 
 * @param {Object} event
 * @returns {undefined}
 */
SocketClient.prototype.onopen = function(event) {
    this.setState('connected');
};

/**
 * Event handler: socket message received
 * 
 * @param {Object} event
 * @returns {undefined}
 */
SocketClient.prototype.onmessage = function(event) {

    // Decode message
    var message = JSON.parse(event.data);
    if (!message) {
        this.log('malformed message received, could not decode');
        this.log(event.data);
        return;
    }

    // Extract method
    var method = message.method || 'nomethod';
    if (method === 'nomethod') {
        this.log('malformed message received, no method');
        this.log(message);
        return;
    }

    // Extract data
    var data = message.data;

    this.log('message received: '+method);

    // Route to local message handler
    var call = 'message_'+method;
    if (typeof(this[call]) === 'function') {
        this[call](data);
    }
    
    this.events.fire('socketmessage', message);
};

/**
 * Event handler: socket error
 * 
 * @param {Object} event
 * @returns {undefined}
 */
SocketClient.prototype.onerror = function(event) {
    
};

/**
 * Event handler: socket closed
 * 
 * @param {Object} event
 * @returns {undefined}
 */
SocketClient.prototype.onclose = function(event) {
    this.log('websocket closed');
    this.retry();
};

/**
 * Send a message to the socket
 * 
 * @param {String} method
 * @param {Object} data
 * @returns {String}
 */
SocketClient.prototype.message = function(method, data) {
    this.socket.send(JSON.stringify({
        "method": method,
        "data": data
    }));
};

/**
 * Register mirror client with server
 * @returns {undefined}
 */
SocketClient.prototype.register = function() {
    this.log("registering");
    var message = this.events.fireReturn('socketregister');
    this.log(message);

    this.message('register', message);
};

/**
 * Message handler: error
 * 
 * @param {Object} data
 * @returns {undefined}
 */
SocketClient.prototype.message_error = function(data) {
    var reason = data.reason || "no reason";
    this.log("server sent error: "+reason);
};

/**
 * Message handler: registered
 * 
 * @param {Object} data
 * @returns {undefined}
 */
SocketClient.prototype.message_registered = function(data) {
    this.log("server acknowledged registration");
    this.setState('registered');
    this.setState('ready');
    
    // Check if a refresh is required
    var serverVersion = data.version;
    if (this.registeredVersion !== null) {
        if (serverVersion !== this.registeredVersion) {
            this.log(" server version changed ("+this.registeredVersion+" -> "+serverVersion+", reloading UI");
            window.location.reload();
        }
    }
    this.registeredVersion = serverVersion;
};

/**
 * Write tagged logs to console
 * 
 * @param {type} message
 * @returns {undefined}
 */
SocketClient.prototype.log = function(message) {
    if (typeof(message) === 'string') {
        console.log('[socket] '+message);
    } else {
        console.log(message);
    }
};

module.exports = SocketClient;