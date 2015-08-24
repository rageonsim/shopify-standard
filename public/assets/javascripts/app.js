/**
 * Javascript App File for 'ShopifyStandard' Project
 */
'use strict'

function App(_init_state) {
  // Integrity Check
  if(!(this instanceof App)) return new App(_state);
  if(!Object.deepExtend) {
    var libScript  = document.createElement("script");
    libScript.type = 'text/javascript';
    libScript.src  = '/assets/javascripts/lib.js';
    libScript.onload = function() {
      console.log("App Library Added, Creating Global App");
      return window.App = window.App || new App(_state);
    };
    document.body.appendChild(libScript);
    console.log("App adding Library...");
    return false;
  }

  // Private Class Variables (prefix with underscore)
  var _this = this,
    _state = null,
    _defaultOptions = {
      "appName": "ShopifyStandard",
      "route"  : {
        "controller": "index",
        "action"    : "index",
        "req_etc" : {},
        "params"  : {}
      }
    },
    _route = null,
    _actions = {};

  // Public Class Variables
  _this.initd = false;
  _this.state = {};

  // Public Methods
  
  _this.setUrl = function(path,state) {
    window.history.pushState(state, path, path);
  }

  // Private Methods
  
  function empty(obj) { return !Object.keys(obj).length; }
  
  function setState(state) {
    _this.state = Object.deepExtend(_defaultOptions,state);
    _state = _this.state;
  }

  function setRoute(route) { _route = route; }

  function doAction(route) {
    if(empty(_actions)) setActions();
    return _actions[route.controller][route.action].apply(_this, [ _state ]);
  }

  function setActions(actions) {
    actions = typeof actions !== "object" ? false : actions;
    // check if already defined if not to be updated
    if(actions==false&&!empty(_actions)) return _actions;
    // define view actions, [controller][action](_state)
    _this.actions = actions==false||empty(_actions) ? {
      "index": {
        "fix-options": function(state) {

        }
      },
      "update": {
        "skus": function(state) {

        },
        "colors": function(state) {
          console.info({"_actions:update:colors":state});
          
        }
      },
      "save": {
        "skus": function(state) {},
        "colors": function(state) {}
      }
    } : actions; // default to above, or set if passed
    _actions = _this.actions;
  }

  // App Constructor | Auto-Loading, keep at end
  (_this.init = function(state) {
    if(_this.initd) return _this; // Object Inited
    _this.initd   = true; // No Double Init Same Object
    setState(state);
    // try oninit callback
    if(typeof _state.oninit  == "function")  _state.oninit.apply(_this, [ _state ]);
    // update the URL if need be
    if(typeof _state.set_url != 'undefined') _this.setUrl(_state.set_url, _state);
    //setRoute.apply(_this.state.route);
    setRoute(_this.state.route);
    //log init data
    console.log({"Route":_route,"App":_this.state});
    // call action method
    doAction(_route);

  }).apply(_this,[_init_state]); // End App Constructor
} // End App Class