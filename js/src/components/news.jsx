
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

var React = require('react');

var News = React.createClass({
    
    is: function() { return 'news'; },
    
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
                <div className="mirror-news"></div>
            );
        }
        
        return (
            <div className="mirror-news">
                <div className="news-stories">
                {this.state.articles.map(function(row, i){
                    return (
                        <div className="news-story" key={row.id}>
                            <i className="news-icon fa fa-newspaper-o"></i> {row.title}
                        </div>
                    );
                })}
                </div>
            </div>
        );
    }
});

module.exports = News;