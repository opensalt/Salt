///////////////////////////////////////////////////////////////////////////////
// UTILITY FUNCTIONS

/** Checks whether a value is empty, defined as null or undefined or "".
 *  Note that 0 is defined as not empty.
 *  @param {*} val - value to check
 *  @returns {boolean}
 */
function empty(val) {
    // note that we need === because (0 == "") evaluates to true
    return ('undefined' === typeof(val) || null === val || "" === val);
}

/** Get the value of an object property,
 *  checking first to see if the object exists, and where the property may be nested several layers deep
 *  argument 0 should be the top-level object we're checking
 *  arguments 1... are keys to check
 *
 *  SHORTCUT: op()
 *
 *  @returns {*}
 */
function objectProperty() {
    let o = arguments[0];
    // go through keys in the arguments
    for (let i = 1; i < arguments.length; ++i) {
        // if o is empty or is not an object, we can't get any property, so return undefined
        if (empty(o) || typeof(o) !== "object") {
            return undefined;
        }
        // get the next level down, which might be another object to check a property of (in which case we'll loop again)
        // or might be the final value to return, which could itself be an object (in which case the loop will end here)
        o = o[arguments[i]];
    }
    // if we get here return whatever we came up with.  It might be an object or a scalar
    return o;
}

export { empty, objectProperty, objectProperty as op };
