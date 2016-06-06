
/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

/**
 * ALICE javascript event framework
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-webui
 */
function Events(){};

/**
 * Register an event binding
 *
 * @param {String} event
 * @param {Function} callback
 * @param {Object} context
 */
Events.prototype.on = function(event, callback, context) {
    this.bindings = this.bindings || {};
    (this.bindings[event] = this.bindings[event] || []).push({fn:callback, ctx:context});
    return this;
};

/**
 * Remove an event hook
 *
 * @param {String} event
 * @param {Function} callback
 * @return {Boolean} successfully removed, or didn't exist
 */
Events.prototype.off = function(event, callback) {
    this.bindings = this.bindings || {};

    if (arguments.length === 0) {
        this.bindings = {};
        return this;
    }

    // No callbacks, do nothing
    var callbacks = this.bindings[event];
    if (!callbacks) {
        return this;
    }

    // Remove all hooks for this event
    if (arguments.length === 1) {
      delete this.bindings[event];
      return this;
    }

    // Remove a single callback
    var cb;
    for (var i = 0; i < callbacks.length; i++) {
        cb = callbacks[i];
        if (cb.fn === callback) {
            callbacks.splice(i, 1);
            break;
        }
    }
    return this;
};

/**
 * Fire an event
 *
 * @param {String} event
 */
Events.prototype.fire = function(event) {
    this.bindings = this.bindings || {};
    var data = [].slice.call(arguments, 1);
    var callbacks = this.bindings[event];

    if (callbacks) {
        callbacks = callbacks.slice(0);
        for (var i = 0, len = callbacks.length; i < len; ++i) {
            callbacks[i].fn.apply(callbacks[i].ctx, data);
        }
    }
};

/**
 * Fire an event and return the final result
 *
 * @param {String} event
 */
Events.prototype.fireReturn = function(event) {
    this.bindings = this.bindings || {};
    var data = [].slice.call(arguments, 1);
    var callbacks = this.bindings[event];

    var returnValue = null;
    if (callbacks) {
        callbacks = callbacks.slice(0);
        for (var i = 0, len = callbacks.length; i < len; ++i) {
            returnValue = callbacks[i].fn.apply(callbacks[i].ctx, data);
        }
    }
    
    return returnValue;
};

/**
 * Return array of callbacks for an event
 *
 * @param {String} event
 * @return {Array}
 */
Events.prototype.callbacks = function(event){
    this.bindings = this.bindings || {};
    return this.bindings[event] || [];
};

/**
 * Check if this event has any callbacks
 *
 * @param {String} event
 * @return {Boolean}
 */
Events.prototype.has = function(event){
    return !! this.callbacks(event).length;
};

module.exports = Events;