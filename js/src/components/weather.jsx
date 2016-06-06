
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

var React = require('react');

var Weather = React.createClass({
    
    is: function() { return 'weather'; },
    
    /**
     * 
     */
    getInitialState: function() {
        return {
            temperature: "unknown"
        };
    },
    
    /**
     * 
     * @param {type} icon
     * @returns {String}
     */
    getIcon: function(icon) {
        switch (icon) {
            case 'clear-day': 
            case 'clear-night':
            case 'wind':
            case 'cloud':
                return icon;
            case 'fog':
                
                return icon;
            case 'rain':
            case 'snow':
                return icon+'-2';
            case 'sleet':
                return 'snow-1';
            case 'partly-cloudy-day':
            case 'partly-cloudy-night':
                return icon+'-2';
            case 'thunderstorm':
                return 'thunderstorm';
            case 'hail':
            case 'tornado':
                return 'alert';
            case 'unknown':
            default:
                return 'unknown';
                break;
        }
    },
    
    /**
     * Render 
     * @returns {undefined}
     */
    render: function(){
        if (this.state.temperature === 'unknown') {
            return (
                <div className="mirror-weather"></div>
            );
        }
        
        var iconName = "weather-icon icon-"+this.getIcon(this.state.icon);
        var pp = Math.round(this.state.precipProbability);
        return (
            <div className="mirror-weather">
                <div className="weather-now">
                    <div className="weather-temperature">{this.state.temperature}&deg;</div>
                    <div className={iconName}></div>
                </div>
                <div className="weather-summary">{this.state.now}</div>
                <div className="weather-prediction">{this.state.today}</div>
                <div className="weather-chance"><i className="fa fa-umbrella"></i> {pp}% <span className="weather-feelslike">({this.state.apparentTemperature}&deg;)</span></div>
            </div>
        );
    }
});

module.exports = Weather;