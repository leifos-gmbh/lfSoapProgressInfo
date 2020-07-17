# lfSoapProgressinfo
This is a plugin for the `SoapHook` plugin slot. The plugin registers a new SOAP method `getProgressInfo` to the ILIAS SOAP server.

Note: The new SOAP method `getExtendedProgressInfo` is only available under the ILIAS client where this plugin is installed. 
The SOAP endpoint MUST include the client-ID as GET parameter, otherwise the method is not found. The SOAP endpoint thus becomes: http://your-ilias-domain.com/webservice/soap/server.php?client_id=<client_id>

## Installation
```
mkdir -p Customizing/global/plugins/Services/WebServices/SoapHook
cd Customizing/global/plugins/Services/WebServices/SoapHook
git clone https://github.com/leifos-gmbh/lfSoapProgressInfo
```

Activate the plugin in ILIAS under `Adiminstration > Plugins`.

## Usage

The new method `getExtendedProgressInfo` requires `sid` and `ref_id` parameters:
* `sid`: A valid sesison-ID obtained via the `login` method
* `ref_id`: Ref-ID of any object that has a learning progress. This object is also referred to as current object.
* `progress_filter`: Integer-Array of filters for different progress levels. Default is all progress levels. (All = 0, In progress = 1, Completed = 2, Failed = 3, Not attempted = 4)
* `assigned_filter`: Integer of filter to get information about sub-objects. Default is only current object. (Only Current = 0, Assigned as LP relevant = 1, All sub-objects = 2)
* `object_types`: String-Array of object types for sub-objects that should be looked up. This has no effect on the current object.

**Example request body:**

```xml
<soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:ilUserAdministration" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
   <soapenv:Header/>
   <soapenv:Body>
      <urn:getExtendedProgressInfo soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
         <sid xsi:type="xsd:string">sdkpok827rgh29jdsadlkd::default</sid>
         <ref_id xsi:type="xsd:int">1287</ref_id>
         <progress_filter xsi:type="urn:intArray" soapenc:arrayType="xsd:int[]"/>
         <assigned_filter xsi:type="xsd:int">2</assigned_filter>
         <object_types xsi:type="urn:stringArray" soapenc:arrayType="xsd:string[]">
           <item xsi:type="string">lso</item>
           <item xsi:type="string">tst</item>
         </object_types>
      </urn:getExtendedProgressInfo>
   </soapenv:Body>
</soapenv:Envelope>
```