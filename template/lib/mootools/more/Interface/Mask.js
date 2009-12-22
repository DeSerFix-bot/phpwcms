//MooTools More, <http://mootools.net/more>. Copyright (c) 2006-2009 Aaron Newton <http://clientcide.com/>, Valerio Proietti <http://mad4milk.net> & the MooTools team <http://mootools.net/developers>, MIT Style License.

var Mask=new Class({Implements:[Options,Events],Binds:["resize"],options:{style:{},"class":"mask",maskMargins:false,useIframeShim:true,iframeShimOptions:{}},initialize:function(b,a){this.target=document.id(b)||document.body;this.target.store("mask",this);this.setOptions(a);this.render();this.inject()},render:function(){this.element=new Element("div",{"class":this.options["class"],id:this.options.id||"mask-"+$time(),styles:$merge(this.options.style,{display:"none"}),events:{click:function(){this.fireEvent("click");if(this.options.hideOnClick){this.hide()}}.bind(this)}});this.hidden=true},toElement:function(){return this.element},inject:function(b,a){a=a||this.options.inject?this.options.inject.where:""||this.target==document.body?"inside":"after";b=b||this.options.inject?this.options.inject.target:""||this.target;this.element.inject(b,a);if(this.options.useIframeShim){this.shim=new IframeShim(this.element,this.options.iframeShimOptions);this.addEvents({show:this.shim.show.bind(this.shim),hide:this.shim.hide.bind(this.shim),destroy:this.shim.destroy.bind(this.shim)})}},position:function(){this.resize(this.options.width,this.options.height);this.element.position({relativeTo:this.target,position:"topLeft",ignoreMargins:!this.options.maskMargins,ignoreScroll:this.target==document.body});return this},resize:function(a,e){var b={styles:["padding","border"]};if(this.options.maskMargins){b.styles.push("margin")}var d=this.target.getComputedSize(b);if(this.target==document.body){var c=window.getSize();if(d.totalHeight<c.y){d.totalHeight=c.y}if(d.totalWidth<c.x){d.totalWidth=c.x}}this.element.setStyles({width:$pick(a,d.totalWidth,d.x),height:$pick(e,d.totalHeight,d.y)});return this},show:function(){if(!this.hidden){return this}this.target.addEvent("resize",this.resize);if(this.target!=document.body){document.id(document.body).addEvent("resize",this.resize)}this.position();this.showMask.apply(this,arguments);return this},showMask:function(){this.element.setStyle("display","block");this.hidden=false;this.fireEvent("show")},hide:function(){if(this.hidden){return this}this.target.removeEvent("resize",this.resize);this.hideMask.apply(this,arguments);if(this.options.destroyOnHide){return this.destroy()}return this},hideMask:function(){this.element.setStyle("display","none");this.hidden=true;this.fireEvent("hide")},toggle:function(){this[this.hidden?"show":"hide"]()},destroy:function(){this.hide();this.element.destroy();this.fireEvent("destroy");this.target.eliminate("mask")}});Element.Properties.mask={set:function(b){var a=this.retrieve("mask");return this.eliminate("mask").store("mask:options",b)},get:function(a){if(a||!this.retrieve("mask")){if(this.retrieve("mask")){this.retrieve("mask").destroy()}if(a||!this.retrieve("mask:options")){this.set("mask",a)}this.store("mask",new Mask(this,this.retrieve("mask:options")))}return this.retrieve("mask")}};Element.implement({mask:function(a){this.get("mask",a).show();return this},unmask:function(){this.get("mask").hide();return this}});