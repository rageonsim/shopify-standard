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
    _actions = {},
    _callbacks = {},
    _sync_ajax = {};

  // Public Class Variables
  _this.initd = false;
  _this.state = {};

  // Public Methods
  
  _this.setUrl = function(path,state) {
    window.history.pushState(state, path, path);
  }

  // public access to private ajax method
  _this.ajax = function(ajax_url, ajax_data, async, method) { return ajax.apply(_this, Array.slice(arguments)); }
  _this.doAction = function(route) { return doAction.apply(_this, [ route ] ); }

  // Private Methods
  
  function empty(obj) { return !Object.keys(obj).length; }
  
  function setState(state) {
    _this.state = Object.deepExtend(_defaultOptions,state);
    _state = _this.state;
  }

  function setRoute(route) { _route = route; }

  function ajax(ajax_url, ajax_data, async, method) {
    // use private class var to que deferred ajax calls (for async=false);
    async  = typeof async  === 'undefined' ?  true  : async;
    method = typeof method === "undefined" ? "POST" : method;
    if(async) {
      return doAjax(ajax_url, ajax_data, method);
    } else {
      if(!!_sync_ajax&&!!_sync_ajax.then) {
        return _sync_ajax.then(doAjax(ajax_url, ajax_data, method));
      } else {
        return (_sync_ajax = doAjax(ajax_url, ajax_data, method));
      }
    }
  }

  function doAjax(ajax_url, ajax_data, method) {
    method = typeof method === "undefined" ? "POST" : method;
    var callbacks = getCallbacks(ajax_url);
    return jQuery.
      ajax({
        url: ajax_url,
        type: method,
        data_type: "json",
        data: ajax_data
      }).
      success(callbacks.success).
      error(callbacks.error).
      complete(callbacks.complete);
  }

  function getCallbacks(path) {
    if(empty(_callbacks)) setCallbacks();
    var path_arr  = path.split("/").slice(1);
    var callbacks = Object.create(_callbacks);
    path_arr.forEach(function(prop, index, arr) {
      callbacks   = callbacks[prop];
    }, _this);
    return callbacks;
  }

  // instead of going all out to make a Callbacks class
  function setCallbacks(callbacks) {
    // console.info("setting callbacks");
    callbacks = typeof callbacks !== "object" ? false : actions;
    if(callbacks==false&&!empty(_callbacks)) return _callbacks;
    _this.callbacks = callbacks==false||empty(callbacks) ? {
      "ajax": {
        "determine": {
          "color": {
            success: function(response, status_str, jqXHR_obj) {
              var var_sku = response.request.params.var_sku,
                  $input  = jQuery("#"+var_sku);
              if($input.hasClass("unedited") && $input.hasClass("undetermined")) {
                $input.data("ajax-suggestion",response.suggestion)
                      .val(response.suggestion).change()
                      .toggleClass("determined undetermined");
              }
              $input.data("ajax-determined",response.suggestion);
            },
            error: function(jqXHR_obj, status_str, error_str) {
              console.log({"error": arguments});
            },
            complete: function(jqXHR_obj, status_str) {
              //console.log({"complete": arguments});
            }
          }
        }
      }
    } : callbacks;
    _callbacks = _this.callbacks;
  }

  // to avoid setting up full Actions clsass
  function setActions(actions) {
    console.log("setting actions");
    actions = typeof actions !== "object" ? false : actions;
    // check if already defined if not to be updated
    if(actions==false&&!empty(_actions)) return _actions;
    // define view actions, [controller][action](_state)
    _this.actions = actions==false||empty(actions) ? {
      "index": {
        "fix-options": function(state) {

        }
      },
      "update": {
        "skus": function(state) {

        },
        "colors": function(state) {
          // console.info({"_actions:update:colors":state});
          var $inputs = jQuery(".ajax-determine-color");
          var $progress = jQuery(".progress-bar");
              $progress.find(".progress-max").text($inputs.length);
          // track edits
          $inputs.
            on("change", function(e) {
              var $input = jQuery(e.target);
              if($input.val().localeCompare($input.data('org-value'))==0) {
                $input.removeClass("edited").addClass("unedited");
              } else {
                $input.removeClass("unedited").addClass("edited");
              }
              var edited = $inputs.filter('.edited').length,
                  total  = $inputs.length,
                  percnt = Math.round((edited/total)*100);
              $progress.css("width",percnt+"%").attr("aria-valuenow",percnt);
              $progress.find(".progress-at").text(edited);
              if(state.auto_advance && percnt==100) {
                $input.parents("form").submit();
              }
            }).
            each(function(index, input) {
              var $input = jQuery(input),
                  $label = $input.siblings("label").first();
              $label.on("click", function(e) {
                $input.val($input.data('org-value'));
              });
            }).
            filter(".undetermined").each(function(index, input) {
              var $input    = jQuery(input),
                  ajax_data = $input.data('ajax-data'),
                  ajax_url  = $input.data('ajax-url');
              ajax_data.cur_val = $input.val();
              $input.data("ajax-deferred", 
                ajax(ajax_url, ajax_data, false) // do callback ^
              );
            });


          
        }
      },
      "save": {
        "skus": function(state) {},
        "colors": function(state) {}
      }
    } : actions; // default to above, or set if passed
    _actions = _this.actions;
  }

  function doAction(route) {
    if(empty(_actions)) setActions();
    // @todo: Need error checking for existing action...
    if(typeof _actions[route.controller] === 'undefined') return false;
    if(typeof _actions[route.controller][route.action] === 'undefined') return false;
    return _actions[route.controller][route.action].apply(_this, [ _state ]);
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