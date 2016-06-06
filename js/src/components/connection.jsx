
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

var React = require('react');

var Loader = require('./loader.jsx');

var Connection = React.createClass({
    
    is: function() { return 'connection'; },
    
    getInitialState: function() {
        return {
            mode: 'offline',
            status: 'waiting'
        };
    },
    
    /**
     * Tick down retry timer
     * 
     */
    tick: function() {
        if (this.state.mode !== 'retrying') {
            clearInterval(this.timer);
            return;
        }
        
        if (this.state.delay < 1) {
            clearInterval(this.timer);
        }
        
        this.setState({
            delay: this.state.delay-1
        });
    },
    
    /**
     * 
     */
    shouldComponentUpdate: function(props, state) {
        if (state.mode === 'retrying' && !this.state.mode !== state.mode) {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
            this.timer = setInterval(this.tick, 1000);
        }
        
        return true;
    },
    
    /**
     * Render
     * @returns {undefined}
     */
    render: function(){
        if (this.state.mode === 'retrying') {
            var seconds = (this.state.delay === 1) ? 'second' : 'seconds';
            return (
                <div className="mirror-connection">
                    <div className="connection-header">
                        <Loader />
                        <div className="connection-status">{this.state.status}</div>
                    </div>
                    <div className="connection-info">Retrying in {this.state.delay} {seconds}</div>
                </div>
            );
        } else {
            return (
                <div className="mirror-connection">
                    <div className="connection-header">
                        <Loader />
                        <div className="connection-status">{this.state.status}</div>
                    </div>
                </div>
            );
        }
    }
});

module.exports = Connection;