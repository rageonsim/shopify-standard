/**
 * Javascript App File for 'ShopifyStandard' Project
 */
'use strict'

function App(_options) {
 	// Integrity Check
 	if(!(this instanceof App)) return new App(_options);
 	if(!Object.deepExtend) {
		var libScript  = document.createElement("script");
		libScript.type = 'text/javascript';
		libScript.src  = '/assets/javascripts/lib.js';
		libScript.onload = function() {
			console.log("App Library Added, Creating Global App");
			return window.App = window.App || new App(_options);
		};
 		document.body.appendChild(libScript);
 		console.log("App adding Library...");
 		return false;
 	}

 	// Class Variables
 	this.initd = false;
 	this.defaultOptions = {
 		"appName": "ShopifyStandard"
 	};
 	this.options = {};

 	// App Constructor
	(this.init = function(options) {
 		if(this.initd) return this; // Object Inited
 		this.initd = true; // No Double Init Same Object
 		this.options = Object.deepExtend(this.defaultOptions,options);
 		if(typeof this.options['oninit'] == "function") this.options['oninit'].apply(this,[this.options]);
 		if(typeof this.options['set_url'] != 'undefined') window.history.pushState({
 			options: this.options
 		}, this.options['set_url'], this.options['set_url']);
 		//log test
		console.log({"App":options});

	}).apply(this,[_options]); // End App Constructor
} // End App Class