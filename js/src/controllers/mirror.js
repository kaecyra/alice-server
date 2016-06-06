
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

var React = require('react');
var ReactDOM = require('react-dom');

var MirrorUI = require('../components/mirrorui.jsx');

/**
 * Mirror Controller
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-webui
 * @param {Object} config
 * @param {SocketClient} client
 * @param {Events} events
 */
function Mirror(config, client, events) {
    
    /**
     * Client config
     * @type {Object}
     */
    this.config = config;
    
    /**
     * SocketClient
     * @type {SocketClient}
     */
    this.client = client;
    
    /**
     * Events
     * @type {Events}
     */
    this.events = events;
    
    /**
     * Client name
     * @type {String}
     */
    this.name = config.name;
    
    /**
     * Client ID
     * @type {String}
     */
    this.id = config.id;
    
    /**
     * List of data sources
     * @type {Array}
     */
    this.sources = config.interface.sources;
    
    /**
     * List of sensor sources
     * @type {Array}
     */
    this.sensors = config.interface.sensors;
    
    /**
     * React UI container
     * @type {ReactComponent}
     */
    this.ui = null;
    
    // Constructor
    
    this.events.on('socketstate', this.proxy(this.receiveState));
    this.events.on('socketmessage', this.proxy(this.receiveMessage));
    
    this.mount();
    
    this.client.start(events);
};

/**
 * Proxy a function to ourself
 * 
 * @param {Function} fn
 * @returns {undefined}
 */
Mirror.prototype.proxy = function(fn) {
    return $.proxy(fn, this);
};

/**
 * Mount react elements
 * 
 * @returns {undefined}
 */
Mirror.prototype.mount = function() {
    this.ui = ReactDOM.render(
        <MirrorUI />,
        document.getElementById('widget-mirror')
    );
};

/**
 * 
 * @param {type} event
 * @returns {undefined}
 */
Mirror.prototype.receiveState = function(event) {
    switch (event.mode) {
        case 'connected':
            this.register();
            break;
            
        case 'ready':
            this.client.message('catchup');
            break;
    }
    
    // Pass state to UI
    if (this.ui) {
        this.ui.setState(event);
    }
};

/**
 * Register this device
 * 
 * @returns {Object}
 */
Mirror.prototype.register = function() {
    this.log('registering');
    
    var message = {
        name: this.name,
        id: this.id,
        settings: this.config.settings
    };
    
    // Attach sources
    message.sources = this.sources;
    
    // Attach sensors
    message.sensors = this.sensors;
    
    this.client.message('register', message);
};

/**
 * Receive a message from the socket server
 * 
 * @param {type} message
 * @returns {undefined}
 */
Mirror.prototype.receiveMessage = function(message) {
    var method = message.method;
    
    var call = 'message_'+method;
    if (typeof(this[call]) === 'function') {
        this.log('handling message: '+call);
        this[call](message.data);
    }
};

/**
 * Message handler: update
 * 
 * @param {Object} update
 * @returns {undefined}
 */
Mirror.prototype.message_update = function(update) {
    var source = update.source;
    var filter = update.filter;
    var data = update.data;
    this.log("received data update: "+source+"/"+filter);
    this.log(data);

    this.ui.handleUpdate(source, data);
};

/**
 * Message handler: sleep
 * 
 * @param {Object} data
 * @returns {undefined}
 */
Mirror.prototype.message_sleep = function(data) {
    this.ui.sleep();
};

/**
 * Message handler: wake
 * 
 * @param {Object} data
 * @returns {undefined}
 */
Mirror.prototype.message_wake = function(data) {
    this.ui.wake();
};

/**
 * Write tagged logs to console
 * 
 * @param {type} message
 */
Mirror.prototype.log = function(message) {
    if (typeof(message) === 'string') {
        console.log('[mirror] '+message);
    } else {
        console.log(message);
    }
};

module.exports = Mirror;