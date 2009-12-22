//MooTools More, <http://mootools.net/more>. Copyright (c) 2006-2009 Aaron Newton <http://clientcide.com/>, Valerio Proietti <http://mad4milk.net> & the MooTools team <http://mootools.net/developers>, MIT Style License.

Fx.Sort=new Class({Extends:Fx.Elements,options:{mode:"vertical"},initialize:function(b,a){this.parent(b,a);this.elements.each(function(c){if(c.getStyle("position")=="static"){c.setStyle("position","relative")}});this.setDefaultOrder()},setDefaultOrder:function(){this.currentOrder=this.elements.map(function(b,a){return a})},sort:function(e){if($type(e)!="array"){return false}var i=0,a=0,c={},h={},d=this.options.mode=="vertical";var f=this.elements.map(function(m,j){var l=m.getComputedSize({styles:["border","padding","margin"]});var n;if(d){n={top:i,margin:l["margin-top"],height:l.totalHeight};i+=n.height-l["margin-top"]}else{n={left:a,margin:l["margin-left"],width:l.totalWidth};a+=n.width}var k=d?"top":"left";h[j]={};var o=m.getStyle(k).toInt();h[j][k]=o||0;return n},this);this.set(h);e=e.map(function(j){return j.toInt()});if(e.length!=this.elements.length){this.currentOrder.each(function(j){if(!e.contains(j)){e.push(j)}});if(e.length>this.elements.length){e.splice(this.elements.length-1,e.length-this.elements.length)}}var b=i=a=0;e.each(function(l,j){var k={};if(d){k.top=i-f[l].top-b;i+=f[l].height}else{k.left=a-f[l].left;a+=f[l].width}b=b+f[l].margin;c[l]=k},this);var g={};$A(e).sort().each(function(j){g[j]=c[j]});this.start(g);this.currentOrder=e;return this},rearrangeDOM:function(a){a=a||this.currentOrder;var b=this.elements[0].getParent();var c=[];this.elements.setStyle("opacity",0);a.each(function(d){c.push(this.elements[d].inject(b).setStyles({top:0,left:0}))},this);this.elements.setStyle("opacity",1);this.elements=$$(c);this.setDefaultOrder();return this},getDefaultOrder:function(){return this.elements.map(function(b,a){return a})},forward:function(){return this.sort(this.getDefaultOrder())},backward:function(){return this.sort(this.getDefaultOrder().reverse())},reverse:function(){return this.sort(this.currentOrder.reverse())},sortByElements:function(a){return this.sort(a.map(function(b){return this.elements.indexOf(b)},this))},swap:function(c,b){if($type(c)=="element"){c=this.elements.indexOf(c)}if($type(b)=="element"){b=this.elements.indexOf(b)}var a=$A(this.currentOrder);a[this.currentOrder.indexOf(c)]=b;a[this.currentOrder.indexOf(b)]=c;return this.sort(a)}});