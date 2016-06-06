
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

var React = require('react');

var Calendar = React.createClass({
    
    is: function() { return 'calendar'; },
    
    /**
     * Get initial state
     * 
     */
    getInitialState: function() {
        return {};
    },
    
    /**
     * Render
     * @returns {undefined}
     */
    render: function(){
        return (
            <div className="mirror-calendar">
                CALENDAR
            </div>
        );
    }
});

module.exports = Calendar;