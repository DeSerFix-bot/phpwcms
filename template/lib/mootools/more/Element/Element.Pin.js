//MooTools More, <http://mootools.net/more>. Copyright (c) 2006-2009 Aaron Newton <http://clientcide.com/>, Valerio Proietti <http://mad4milk.net> & the MooTools team <http://mootools.net/developers>, MIT Style License.

(function(){var a=false;window.addEvent("domready",function(){var b=new Element("div").setStyles({position:"fixed",top:0,right:0}).inject(document.body);a=(b.offsetTop===0);b.dispose()});Element.implement({pin:function(d){if(this.getStyle("display")=="none"){return null}var f,b=window.getScroll();if(d!==false){f=this.getPosition();if(!this.retrieve("pinned")){var h={top:f.y-b.y,left:f.x-b.x};if(a){this.setStyle("position","fixed").setStyles(h)}else{this.store("pinnedByJS",true);this.setStyles({position:"absolute",top:f.y,left:f.x}).addClass("isPinned");this.store("scrollFixer",(function(){if(this.retrieve("pinned")){var i=window.getScroll()}this.setStyles({top:h.top.toInt()+i.y,left:h.left.toInt()+i.x})}).bind(this));window.addEvent("scroll",this.retrieve("scrollFixer"))}this.store("pinned",true)}}else{var g;if(!Browser.Engine.trident){var e=this.getParent();g=(e.getComputedStyle("position")!="static"?e:e.getOffsetParent())}f=this.getPosition(g);this.store("pinned",false);var c;if(a&&!this.retrieve("pinnedByJS")){c={top:f.y+b.y,left:f.x+b.x}}else{this.store("pinnedByJS",false);window.removeEvent("scroll",this.retrieve("scrollFixer"));c={top:f.y,left:f.x}}this.setStyles($merge(c,{position:"absolute"})).removeClass("isPinned")}return this},unpin:function(){return this.pin(false)},togglepin:function(){this.pin(!this.retrieve("pinned"))}})})();