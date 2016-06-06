
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

var React = require('react');

/**
 * 
 */
var Special = React.createClass({
    
    is: function() { return 'special'; },
    
    /**
     * Get initial state
     */
    getInitialState: function() {
        return {
            count: 0,
            articles: []
        };
    },
    
    /**
     * Render
     * @returns {undefined}
     */
    render: function(){
        if (!this.state.count) {
            return (
                <div className="mirror-special"></div>
            );
        }
        
        return (
            <div className="mirror-special">
                <div className="special-items">
                {this.state.articles.map(function(row, i){
                    return (
                        <div className="special-story" key={row.id}>
                            <i className="special-icon fa fa-newspaper-o"></i> {row.title}
                        </div>
                    );
                })}
                </div>
            </div>
        );
    }
});

module.exports = Special;