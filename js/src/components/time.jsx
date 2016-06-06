
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

var React = require('react');
var moment = require('moment');

var Time = React.createClass({
    
    is: function() { return 'time'; },
    
    /**
     * Format a moment
     * 
     * @param {type} now
     * @returns {timeAnonym$0.getFormatted.timeAnonym$1}
     */
    getFormatted: function(now) {
        return {
            time: now.format('h:mm'),
            month: now.format('MMMM Do'),
            day: now.format('dddd')
        };
    },
    
    /**
     * Get initial state
     * 
     */
    getInitialState: function() {
        return {epoch: moment().unix()};
    },
    
    /**
     * Count-up tick
     * 
     * @returns {undefined}
     */
    tick: function() {
        this.setState({epoch: this.state.epoch+1});
    },
    
    /**
     * Tick starter
     * 
     * @returns {undefined}
     */
    componentDidMount: function() {
        this.interval = setInterval(this.tick, 1000);
    },
    
    /**
     * 
     * @returns {undefined}
     */
    componentWillUnmount: function() {
        clearInterval(this.interval);
    },
    
    /**
     * Render
     * @returns {undefined}
     */
    render: function(){
        var format = this.getFormatted(moment());
        return (
            <div className="mirror-time">
                <div className="time-time">{format.time}</div>
                <div className="time-day">{format.day}</div>
                <div className="time-month">{format.month}</div>
            </div>
        );
    }
});

module.exports = Time;