
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

var React = require('react');

var Dates = React.createClass({
    
    is: function() { return 'dates'; },
    
    /**
     * Get initial state
     */
    getInitialState: function() {
        return {
            count: 0,
            dates: []
        };
    },
    
    /**
     * Render
     * @returns {undefined}
     */
    render: function(){
        if (!this.state.count) {
            return (
                <div className="mirror-dates"></div>
            );
        }
        
        return (
            <div className="mirror-dates">
                <div className="dates-dates">
                {this.state.dates.map(function(row, i){
                    var icon = 'calendar';
                    switch (row.event) {
                        case 'birthday':
                            icon = 'birthday-cake';
                            break;
                            
                        case 'cute':
                        case 'anniversary':
                            icon = 'heart';
                            break;
                    }
                    var iconClass = "date-icon fa fa-"+icon+" date-icon-"+row.event;
                    
                    return (
                        <div className="date-story" key={row.id}>
                            <i className={iconClass}></i>
                            <div className="date-message">{row.fmessage}</div>
                            <div className="date-name">{row.name}</div>
                        </div>
                    );
                })}
                </div>
            </div>
        );
    }
});

module.exports = Dates;