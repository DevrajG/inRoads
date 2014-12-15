"use strict";
var MAPDISPLAY = {
    map: new ol.Map({
        target: 'map',
        layers: [ new ol.layer.Tile({source: new ol.source.OSM()}) ],
        view: new ol.View({
            center: ol.proj.transform([PHP.DEFAULT_LONGITUDE, PHP.DEFAULT_LATITUDE], 'EPSG:4326', 'EPSG:3857'),
            zoom: 14
        })
    }),
    /**
    * The features are not added to a regular vector layer/source,
    * but to a feature overlay which holds a collection of features.
    * This collection is passed to the modify and also the draw
    * interaction, so that both can add or modify features.
    */
    featureOverlay: new ol.FeatureOverlay({
        style: new ol.style.Style({
            fill:   new ol.style.Fill({color: 'rgba(255,255,255,0.2)'}),
            stroke: new ol.style.Stroke({color:'#ffcc33', width:2}),
            image:  new ol.style.Circle({radius:7, fill: new ol.style.Fill({color:'#ffcc33'})})
        })
    }),
    wktFormatter: new ol.format.WKT(),
    /**
     * Takes a WKT string and renders it on the map
     *
     * This replaces existing features on the map with new
     * features declared in the wkt string.
     *
     * @param string wkt
     */
    loadWkt: function (wkt) {
        if (wkt) {
            var feature = MAPDISPLAY.wktFormatter.readFeature(wkt);
            feature.getGeometry().transform('EPSG:4326', 'EPSG:3857');
            MAPDISPLAY.featureOverlay.setFeatures(new ol.Collection([feature]));
        }
    },
    /**
    * Reads features out of the FeatureOverlay and converts them to WSG84 WKT
    *
    * @return string
    */
    getWkt: function () {
        var clones    = [],
            features  = MAPDISPLAY.featureOverlay.getFeatures().getArray(),
            len = features.length,
            i   = 0,
            wkt = '';

        if (len) {
            for (i=0; i<len; i++) {
                clones[i] = features[i].clone();
                clones[i].getGeometry().transform('EPSG:3857', 'EPSG:4326');
            }
            wkt = MAPDISPLAY.wktFormatter.writeFeatures(clones);
        }
        return wkt;
    }
};
MAPDISPLAY.featureOverlay.setMap(MAPDISPLAY.map);

// Load any initial data the webpage specifies.
if (PHP.mapdata) { MAPDISPLAY.loadWkt(PHP.mapdata); }
