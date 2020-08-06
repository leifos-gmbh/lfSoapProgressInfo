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

The new method `getExtendedProgressInfo` requires `sid` and `ref_id` parameter-values:
* `sid`: A valid sesison-ID obtained via the `login` method
* `ref_id`: Ref-ID of any object that has a learning progress. This object will here be referred to as current object.
* `progress_filter`: Integer-Array of filters for different progress levels. Default is all progress level information will be returned.
* `assigned_filter`: Integer of filter to get information about sub-objects. Default is only the current object information will be returned.
* `object_types`: String-Array of object types for sub-objects that should be looked up. This has no effect on the current object.

It should be noted that while the values of the filter parameters are optional, the parameters themselves have to be set.

### Progress-Filters

ILIAS-Name                    | Value | Description
------------------------------|-------|------------
PROGRESS_FILTER_ALL           | 0 | Returns the data on ALL users for the given object.
PROGRESS_FILTER_IN_PROGRESS   | 1 | Returns the data on users with the status “In Progress” for the given object.
PROGRESS_FILTER_COMPLETED     | 2 |	Returns the data on users with the status “Completed” for the given object.
PROGRESS_FILTER_FAILED        | 3 | Returns the data on users with the status “Failed” for the given object.
PROGRESS_FILTER_NOT_ATTEMPTED | 4 | Returns the data on users with the status “Not Attempted” for the given object.

### Assign-Filters

ILIAS-Name                    | Value | Description
------------------------------|-------|------------
ASSIGN_FILTER_CURRENT         | 0 | Returns the data of the current given object.
ASSIGN_FILTER_ASSIGNED        | 1 | Returns the data of the current given object and all sub-objects that are assigned as relevant for learning progress of the main object.
ASSIGN_FILTER_ALL             | 2 | Returns the data of the current given object and all sub-objects that can have a learning progress.

###Object-Types

Type | Name 
-----|-----------
crs  | Course
grp  | Group
fold | Folder
lm   | Learning Module ILIAS
htlm | Learning Module HTML     
sahs | Learning Module SCORM    
tst  | Test                     
exc  | Exercise                 
sess | Session                  
svy  | Survey                  
file | File                     
mcst | Mediacast                
prg  | Study Programme          
iass | Individual Assessment
copa | Content Page
lso  | Learning Sequence

When creating a new object, the type can also be read in the url as the parameter "new_type" in the creation screen

###Plugin Object-Types

If a repository plugin is installed that supprts learning progress and an object of it has been created, it can also be set as an object_type filter.

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

##Error Codes

Code | Error description | Possible reason and resolution
-----|-------------------|-------------------------------
50   | Authentication failed | The stated SID is not correct. <br/> Is the login data (client, login, password) correct? <br/> Was the SID transmitted correctly? <br/>Is the session yet active?
52   | Invalid progress filter given | The given progress filter is not in the list of possible progress filters. </br> Possible progress filters are 0 to 4. Should no progress filter get set, filter value 0 will be used as default (see Usage for more information).
53   | Invalid assign filter given | The given filter type is not in the list of possible filter types. </br> Possible filter types are 0 to 2. Should no assign filter get set, filter value 0 will be used as default (see Usage for more information).
54   | Invalid reference id [ref_id] given | The given reference id is not correct. <br/> Check the reference id on your system.
56   | Learning progress not available for objects of type [obj_type] | The object with the given reference id does not support learning progress. <br/> Choose an appropriate object.
58   | No Permission to access learning progress in this object | User does not have RBAC permissions for the given object. <br/> Check if the user has the permission "Edit Learning Progress" in the corresponding object.
60   | Learning progress not enabled in ILIAS | Check if Learning Progress is enabled in Administration.