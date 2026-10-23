Autocomplete API
The Autocomplete API is designed to enhance search experiences in frontend applications, offering instant place suggestions in search bars and form fields as users type, recognizing both partial and full words for a streamlined and efficient user interaction.

Try the Autocomplete demo



Use Autocomplete in a Map


If you use LocationIQ Maps with either Leaflet or Maplibre libraries, you can add LocationIQ's Autocomplete as a plugin instantly!

Leaflet JS



This plugin offers additional customization which can be viewed here.

Maplibre JS



Use Autocomplete in a Form



Brief Overview of Request / Response Parameters


The information presented below is a condensed summary. For a more detailed and exhaustive list of parameters, refer to API Reference Page.

Request


Request Format


Requests can be sent to the following endpoint:


 https://api.locationiq.com/v1/autocomplete?key=YOUR_ACCESS_TOKEN&q=SEARCH_STRING
Requests
The Autocomplete API endpoint offers an Anycast IP address and route user requests to a datacenter closest to them. You can still manually specify a region similar to other LocationIQ endpoints, but in the interest of end-user experience, we don't recommended such a configuration.
Request Example


cURL
PHP
Python
JavaScript

curl --request GET \
  --url 'https://api.locationiq.com/v1/autocomplete?key=YOUR_ACCESS_TOKEN&q=Empire'
Filtered Request Examples


Search only for countries:


curl --request GET \
  --url 'https://api.locationiq.com/v1/autocomplete?key=YOUR_ACCESS_TOKEN&q=United&layers=country'
Limit search to the United States and Canada:


curl --request GET \
  --url 'https://api.locationiq.com/v1/autocomplete?key=YOUR_ACCESS_TOKEN&q=Tor&countrycodes=us,ca'
Search only for cities while excluding villages:


curl --request GET \
  --url 'https://api.locationiq.com/v1/autocomplete?key=YOUR_ACCESS_TOKEN&q=Lon&layers=city&tag=!place:village'
Autocomplete Usage
The Autocomplete API endpoint is intended for use where predictive, typeahead functionality is necessary. It is not designed to be compatible with Search/ Forward Geocoding endpoint.
Required Parameters


Name	Description	Required	Values
key	Your API Key	Yes	<string>
q	Query string to search for
Maximum length: 200	Yes	<string>
Frequently used parameters (optional):


Name	Description	Values
limit	Limit the number of returned results. Default is 10.	[1 to 20]
countrycodes	Limit search to a list of countries. [Read more.]	
layers	Restrict results to specific layers. Supported values are road, neighbourhood, suburb, city, county, state, country, and postcode. Multiple values can be specified as a comma-separated list. layers=postcode works only when it is the sole layer and tag is not set.	
normalizecity	For responses with no city value in the address section, the next available element in this order - city_district, locality, town, borough, municipality, village, hamlet, quarter, neighbourhood - from the address section will be normalized to city. Defaults to 0.	[0 |1]
accept-language	Preferred language for showing search results. Defaults to en. Unlike Search and Reverse endpoints, the Autocomplete endpoint supports only a single 2 digit language code. The Autocomplete endpoint currently supports the following languages: English en, Czech cs, Dutch nl, French fr, German de, Indonesian id, Italian it, Norwegian no, Polish pl, Spanish es, Russian ru, Swedish svand Ukrainian uk. If you'd like us to add support for a specific language, please reach out. [Read more.]	[en]
List of all input parameters available on the API Reference page
Response


Response Example



[
    {
        "place_id": "0",
        "osm_id": "34633854",
        "osm_type": "way",
        "licence": "https://locationiq.com/attribution",
        "lat": "40.7484284",
        "lon": "-73.985654619873",
        "boundingbox": [
            "40.7479226",
            "40.7489422",
            "-73.9864855",
            "-73.9848259"
        ],
        "class": "tourism",
        "type": "attraction",
        "display_name": "Empire State Building, 350, 5th Avenue, New York City, New York, 10018, United States of America",
        "display_place": "Empire State Building",
        "display_address": "350, 5th Avenue, New York City, New York, 10018, United States of America",
        "address": {
            "name": "Empire State Building",
            "house_number": "350",
            "road": "5th Avenue",
            "city": "New York City",
            "state": "New York",
            "postcode": "10018",
            "country": "United States of America"
        }
    },
    {
        "place_id": "0",
        "osm_id": "34633854",
        "osm_type": "way",
        "licence": "https://locationiq.com/attribution",
        "lat": "40.7484284",
        "lon": "-73.985654619873",
        "boundingbox": [
            "40.7479226",
            "40.7489422",
            "-73.9864855",
            "-73.9848259"
        ],
        "class": "office",
        "type": "yes",
        "display_name": "Empire State Building, 350, 5th Avenue, New York City, New York, 10018, United States of America",
        "display_place": "Empire State Building",
        "display_address": "350, 5th Avenue, New York City, New York, 10018, United States of America",
        "address": {
            "name": "Empire State Building",
            "house_number": "350",
            "road": "5th Avenue",
            "city": "New York City",
            "state": "New York",
            "postcode": "10018",
            "country": "United States of America"
        }
    }
]
Response Parameters


Name	Description
lat	The Latitude of this element
lon	The Longitude of this element
display_place	Only the name part of the address; if the type is a city, just the city's name. If the type is highway, just the road's name. This is helpful when a client library wants to display this information separately.
display_address	The complete address without the text already present in display_place
display_name	The display name of this element, with complete address
osm_type	The type of this result. One of node,way or relation. Omitted for results sourced from non-OSM datasets.
osm_id	The corresponding OSM ID of this result. Omitted for results sourced from non-OSM datasets.
List of all response parameters available on the API Reference page https://docs.locationiq.com/reference/autocomplete-2