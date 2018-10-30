/**
 *  Generated with https://jsonschema.net/#/
 * @type type
 */
var schema = {
  "definitions": {},
  "$schema": "http://json-schema.org/draft-07/schema#",
  "$id": "http://example.com/root.json",
  "type": "object",
  "title": "The Root Schema",
  "required": [
    "title",
    "rdfTypes",
    "fedoraCreateDate"
  ],
  "properties": {
    "title": {
      "$id": "#/properties/title",
      "type": "string",
      "title": "The Title Schema",
      "default": "",
      "pattern": "^(.*)$"
    },
    "rdfTypes": {
      "$id": "#/properties/rdfTypes",
      "type": "array",
      "title": "The Rdftypes Schema",
      "items": {
        "$id": "#/properties/rdfTypes/items",
        "type": "string",
        "title": "The Items Schema",
        "default": "",
        "pattern": "^(.*)$"
      }
    },
    "fedoraCreateDate": {
      "$id": "#/properties/fedoraCreateDate",
      "type": "string",
      "title": "The Fedoracreatedate Schema",
      "default": "",
      "pattern": "^(.*)$"
    }
  }
};
pm.test('Schema Validation', function() {
    var result=tv4.validateResult(JSON.parse(responseBody), schema);
    if(!result.valid){
        console.log(result);
    }
    pm.expect(result.valid).to.be.true;
});


pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});


pm.test("Content-Type is present", function () {
    pm.response.to.have.header("Content-Type");
});

tests["Content-Type header is JSON"] = postman.getResponseHeader("Content-Type") === "application/json";

// example using pm.expect()
pm.test("checkIdentifier complex test", function () { 
    var jsonData = pm.response.json();
    pm.expect(jsonData['title']).to.be.a('string').and.not.empty;
    pm.expect(jsonData['rdfTypes']).to.be.a('array').and.not.empty;
    pm.expect(jsonData['fedoraCreateDate']).to.be.a('string').and.not.empty;
});
