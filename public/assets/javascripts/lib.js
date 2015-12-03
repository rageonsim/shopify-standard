/**
 * Library Javascript Functions / Prototypes for ShopifyStandard Project
 */

/**
 * String Prototypes (replaceAll and google category (from excel paste))
 */

if(!"bookmarket-to-add-color") {
  $("button:contains('Edit options')").trigger("click");
  setTimeout(function() {
    $(document.querySelectorAll("a.btn.add-option")[0]).trigger("click");
    $(document.querySelectorAll("[placeholder='Default Color']")[0]).val(prompt("Color?")).change();
    $("input[type='submit'].btn.btn-primary").first().trigger("click");
    window.close();
  }, 600);
}
 

String.prototype.replaceAll = function(find, replace) {
  return this.replace(new RegExp(find, 'g'), replace);
};

String.prototype.gc = function(){
  var ta = document.getElementById('text');
  var nt = this.replaceAll("	"," > ").toLowerCase();
  ta.value = nt;
  console.log([ta.value,nt]);
  ta.select();
  return nt;
};

Object.deepExtend = function(destination, source) {
  for (var property in source) {
    if (source[property] && source[property].constructor &&
     source[property].constructor === Object) {
      destination[property] = destination[property] || {};
      arguments.callee(destination[property], source[property]);
    } else {
      destination[property] = source[property];
    }
  }
  return destination;
};

function jumpSkuFixAssumeU() {
  $("tr[class$='row']").each(function(index,row) {
    $group = $(row).find("input[name$='group]']");
    $size  = $(row).find("input[name$='size]']");
    $special = $(row).find("input[name$='special]']");
    $special.val($size.val().replace(/.(.*)/,'$1')+$special.val());
    $size.val($group.val()+$size.val().charAt(0));
    $group.val("U");
  });
}

// <body>
//   <textarea id="text" style="margin: 0px; width: 100%; height: 200px;font-size: 115%;"></textarea>
//   <button onclick="document.getElementById('text').value.gc()" style="float:right;height:100px;width: 25em;">Do It!</button>
// </body>

String.prototype.decode = function(quote_style) {
	string = this;
	quote_stlye = typeof(quote_style)=="undefined"?'ENT_QUOTES':quote_style;
  //       discuss at: http://phpjs.org/functions/htmlspecialchars_decode/
  //      original by: Mirek Slugen
  //      improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  //      bugfixed by: Mateusz "loonquawl" Zalega
  //      bugfixed by: Onno Marsman
  //      bugfixed by: Brett Zamir (http://brett-zamir.me)
  //      bugfixed by: Brett Zamir (http://brett-zamir.me)
  //         input by: ReverseSyntax
  //         input by: Slawomir Kaniecki
  //         input by: Scott Cariss
  //         input by: Francois
  //         input by: Ratheous
  //         input by: Mailfaker (http://www.weedem.fr/)
  //       revised by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // reimplemented by: Brett Zamir (http://brett-zamir.me)
  //        example 1: htmlspecialchars_decode("<p>this -&gt; &quot;</p>", 'ENT_NOQUOTES');
  //        returns 1: '<p>this -> &quot;</p>'
  //        example 2: htmlspecialchars_decode("&amp;quot;");
  //        returns 2: '&quot;'

  var optTemp = 0,
    i = 0,
    noquotes = false;
  if (typeof quote_style === 'undefined') {
    quote_style = 2;
  }
  string = string.toString()
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>');
  var OPTS = {
    'ENT_NOQUOTES'          : 0,
    'ENT_HTML_QUOTE_SINGLE' : 1,
    'ENT_HTML_QUOTE_DOUBLE' : 2,
    'ENT_COMPAT'            : 2,
    'ENT_QUOTES'            : 3,
    'ENT_IGNORE'            : 4
  };
  if (quote_style === 0) {
    noquotes = true;
  }
  if (typeof quote_style !== 'number') {
    // Allow for a single string or an array of string flags
    quote_style = [].concat(quote_style);
    for (i = 0; i < quote_style.length; i++) {
      // Resolve string input to bitwise e.g. 'PATHINFO_EXTENSION' becomes 4
      if (OPTS[quote_style[i]] === 0) {
        noquotes = true;
      } else if (OPTS[quote_style[i]]) {
        optTemp = optTemp | OPTS[quote_style[i]];
      }
    }
    quote_style = optTemp;
  }
  if (quote_style & OPTS.ENT_HTML_QUOTE_SINGLE) {
    string = string.replace(/&#0*39;/g, "'"); // PHP doesn't currently escape if more than one 0, but it should
    // string = string.replace(/&apos;|&#x0*27;/g, "'"); // This would also be useful here, but not a part of PHP
  }
  if (!noquotes) {
    string = string.replace(/&quot;/g, '"');
  }
  // Put this in last place to avoid escape being double-decoded
  string = string.replace(/&amp;/g, '&');

  return string;
};

