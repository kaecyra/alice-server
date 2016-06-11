
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

var React = require('react');

var Messages = React.createClass({
    
    is: function() { return 'messages'; },
    
    /**
     * Get initial state
     */
    getInitialState: function() {
        return {
            count: 0,
            messages: []
        };
    },
    
    /**
     * Render
     * @returns {undefined}
     */
    render: function(){
        if (!this.state.count) {
            return (
                <div className="mirror-messages"></div>
            );
        }
        
        return (
            <div className="mirror-messages">
                <div className="messages-messages">
                {this.state.messages.map(function(row, i){
                    var icon = 'comment-o';
                    var iconClass = "message-icon fa fa-"+icon;
                    
                    return (
                        <div className="message-story" key={row.id}>
                            <div className="message-message">{row.message}</div>
                            <div className="message-from">{row.contact.nick}</div>
                        </div>
                    );
                })}
                </div>
            </div>
        );
    }
});

module.exports = Messages;