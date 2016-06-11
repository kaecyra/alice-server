
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

var React = require('react');
var ReactDOM = require('react-dom');

var Connection = require('./connection.jsx');

var Time = require('./time.jsx');
var Weather = require('./weather.jsx');
var News = require('./news.jsx');
var Dates = require('./dates.jsx');
var Messages = require('./messages.jsx');

/**
 * MirrorUI component
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-webui
 */
var MirrorUI = React.createClass({
    
    /**
     * List of my components
     */
    mine: {},

    /**
     * Sleep the mirror
     * 
     * @returns {undefined}
     */
    sleep: function() {
        jQuery('body').addClass('mirror-sleep');
    },

    /**
     * Wake the mirror
     * 
     * @returns {undefined}
     */
    wake: function() {
        jQuery('body').removeClass('mirror-sleep');
    },
    
    /**
     * Get initial mirror state
     */
    getInitialState: function() {
        return {
            mode: 'offline',
            status: 'connecting',
            update: true
        };
    },
    
    /**
     * Receive mirror update message
     * 
     */
    handleUpdate: function(source, data) {
        if (this.mine[source]) {
            this.mine[source].setState(data);
        }
    },
    
    /**
     * Register component ref
     * 
     */
    registerComponent: function(source, component) {
        if (!component) {
            delete this.mine[source];
        }
        this.mine[source] = component;
    },
    
    /**
     * Intercept state
     * 
     */
    componentDidUpdate: function() {

        // If we are not ready, tell the connection about the current state
        if (this.state.mode !== 'ready') {
            this.handleUpdate('connection', this.state);
        }
        
    },
    
    /**
     * Render JSX on state change
     */
    render: function() {
        if (this.state.mode == 'ready') {
        
            return (
                <div className="mirror">
                    <div className="row">

                        <div className="column column-4 left">
                            <Weather ref={(ref) => { this.registerComponent('weather', ref); }} />
                        </div>

                        <div className="column column-4"></div>

                        <div className="column column-4 right">
                            <Time ref={(ref) => { this.registerComponent('time', ref); }} />
                        </div>

                    </div>

                    <div className="row">

                        <div className="column column-8">
                            <News ref={(ref) => { this.registerComponent('news', ref); }} />
                        </div>

                    </div>

                    <div className="row">

                        <div className="column column-8">
                            <Messages ref={(ref) => { this.registerComponent('messages', ref); }} />
                        </div>

                    </div>
            
                    <div className="row">
                    
                        <div className="column column-12">
                            <Dates ref={(ref) => { this.registerComponent('dates', ref); }} />
                        </div>
                
                    </div>
                </div>
            );
    
        } else {
            
            return(
                <div className="mirror">
                    <div className="row special-max">

                        <div className="column column-3"></div>

                        <div className="column column-6 middle center">
                            <Connection ref={(ref) => { this.registerComponent('connection', ref); }} />
                        </div>
                
                        <div className="column column-3"></div>
                
                    </div>
                </div>
            );
            
        }
    }
    
});

module.exports = MirrorUI;