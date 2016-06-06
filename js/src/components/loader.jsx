
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

var React = require('react');

var Loader = React.createClass({
    
    is: function() { return 'loader'; },
    
    /**
     * Render
     * @returns {undefined}
     */
    render: function(){
        return (
            <div className="loader-container">
                <div className="spinner">
                    <div className="cube1"></div>
                    <div className="cube2"></div>
                </div>
            </div>
        );
    }
});

module.exports = Loader;