PIM Connector
=============

Extension to facilitate import from PIM to TYPO3 records.

Step-by-step guide to configuring imports
-----------------------------------------

An import consists of the following components:

1. A `Server` configuration with one or more modules
2. At least one `Module` which configures a named PIM `connector`
3. A mapping class implementing `\Crossmedia\Fourallportal\Mapping\MappingInterface`
4. Registration of the mapping class and optional column mapping information

The following sections describes how to configure and create each of those components

Furthermore, to import Entities you must of course set up everything required to operate
this Entity in Extbase: SQL schema, TCA, PHP class, proper annotations, and a Repository.
This documentation does not cover the Entity setup (but you may refer to the official TYPO3
documentation for further information about this subject).

The `Server` component
----------------------

In order to communicate with the remote API, a `Server` configuration is required. To create
a `Server` configuration you can use the `4AllPortal` backend module and click the plus icon.

The `Server` needs to be configured with the following information:

* A customer domain
* A customer name
* A username for login
* A password for login
* At least one `Module`

All of the above information will be delivered by the PIM service administrator.

Adding a `Module` component
---------------------------

When configuring a `Server` you must add one or more `Module` components. Each `Module` may
present different configuration fields depending on the type of the `Module` but all share a
few common fields:

* A connector name
* A mapping class selection box
* A storage folder selection box

Only the first of these values, the connector name, is provided by the PIM service admin. The
possible values are not documented here and depend entirely on the PIM service.

The remaining fields are used to configure how TYPO3 handles the connector.

Additional fields may be shown based on the selected mapping class.

#### The mapping class

In order to perform imports from a given connector, a mapping class is needed. This class
contains a few basic instructions for how to store the data (essentially, it knows the class
name of the Repository and therefore Entity it handles) as well as a handful of methods which
can be implemented if it is necessary to change the way the mapping happens by default.

A vanilla mapping class can look as simple as this:

```php
<?php
namespace Crossmedia\Products\Fourallportal;

use Crossmedia\Products\Domain\Repository\ProductRepository;
use Crossmedia\Fourallportal\Mapping\AbstractMapping;

class ProductMapping extends AbstractMapping
{
    /**
     * @var string
     */
    protected $repositoryClassName = ProductRepository::class;
}
```

Which is all that is needed in order to import data from the PIM service. Once the class is
created it must be registered:

```php
<?php
// ext_localconf.php of custom extension adding a mapper class
\Crossmedia\Fourallportal\Mapping\MappingRegister::registerMapping(
    \Crossmedia\Products\Fourallportal\ProductMapping::class
);
```

By default, a mapping class will attempt to map properties onto local Entity objects when
the names of columns in the PIM data set matches the property names of the Entity. For
example, a field named `title` in the PIM data will automatically be assigned to the `title`
property of the Entity handled by the mapping class.

**As far as possible, local columns and property names should match the PIM data columns
except where this conflicts with naming schemes**.

However, it isn't always possible to rely on a 1:1 mapping of columns - which is why the
mapping class registration method takes a second argument that is a column map:

```php
<?php
// Still in ext_localconf.php of custom extension
\Crossmedia\Fourallportal\Mapping\MappingRegister::registerMapping(
    \Crossmedia\Products\Fourallportal\ProductMapping::class,
    array(
        'emission_class' => 'emissionclass',
        'product_cat' => 'categories',
    )
);
```

The second argument is an associative array where the keys are the PIM data column names
and the value is the target property name on the Entity.

Conversion of input data to proper TYPO3 data types is then done based on 1) the type of
the PIM input value and 2) the expected type of the property on the Entity.


#### Mapping class check() method

A special method exists on the MappingInterface which can be implemented to perform checks
on the mapping routines or objects specific to the mapping, in the backend module's server
inspection view.

A mapping class with a check method could be as simple as the following:

```php
<?php
namespace Crossmedia\Products\Fourallportal;

use Crossmedia\Products\Domain\Repository\ProductRepository;
use Crossmedia\Fourallportal\Mapping\AbstractMapping;
use Crossmedia\Fourallportal\Service\ApiClient;
use Crossmedia\Fourallportal\Domain\Model\Module;

class ProductMapping extends AbstractMapping
{
    /**
     * @var string
     */
    protected $repositoryClassName = ProductRepository::class;
    
    public function check(ApiClient $client, Module $module, array $status)
    {
        // Perform a check that the Module is configured correctly to map this type.
        // The called method is an example, not an API method. You can do these checks
        // any way you prefer, as long as 
        if (!$this->myMethodToCheckModuleConfiguration($module)) {
            $status['class'] = 'danger'; // Overrides the CSS class for the Module info.
            $status['message'] .= '<p class="text-danger">The module was misconfigured!</p>';
            // Normally you'd provide more feedback - this is just an example. String
            // is simply appended to previous message(s), as HTML.
        }
        
        // Also, perform standard checks that the mapping configuration array is
        // consistent and targets existing properties which have setter methods.
        return parent::check($client, $module, $status);
        // Note: if you do not return parent::check() output, instead return $status
    }
}
```

Implement this method if your mapping class needs to do additional verification of things
like comparing the Module's configuration with API responses to detect misconfiguration.

For example, the FalMapping mapper checks if the shell path trimming and asset downloads
function correctly - and skips the check for mapping configuration because there is no true
model instance that can be analysed (FAL mapping happens through FAL, not Extbase models).


#### Data type conversion

All conversion of data types happens through the `TypeConverter` pattern in TYPO3. To know
how to convert a value, the mapping class looks at the Entity's property and attempts to
determine the type based on various Reflection analysis. It understands the following type
declaration strategies ranging from most to least trustworthy:

* Method signature of setter method demanding a specific type (including PHP7 strict types)
* If it exists, a `@param` annotation on the setter method is the second best option given
  that the setter will be used to set the value (thus potentially accepting a different type
  than the actual property it sets)
* If it exists, a `@var` annotation on the property of the Entity

If none of the above yields a result the import fails with an exception.

Once the desired target data type is determined, the mapping class uses a limited subset of
the features of the `PropertyMapper` to detect a `TypeConverter` that accepts the input data
type and yields the expected output type.

#### The purpose of `TypeConverters`

The choice of `TypeConverters` to do the actual conversion is a way to approximate how TYPO3
itself would attempt to convert a value - and supporting any third party converters through
the TYPO3 API instead of inventing a new API solely for this purpose.

`TypeConverter`s are fairly simple to create in that they have one main method and two
properties that govern which types it accepts and yields. For convenience, a shared base
class for PIM-aware type conversion is added, making new converters excessively simple:

```php
<?php
namespace Crossmedia\Products\TypeConverter;

use Crossmedia\Products\Domain\Model\Product;

class ProductTypeConverter extends AbstractUuidAwareObjectTypeConverter implements PimBasedTypeConverterInterface
{
    /**
     * @var string
     */
    protected $targetType = Product::class;
}
```

This `TypeConverter` only needs to declare which target type it supports - everything else
is handled by the base class.

The base class provides a few essential features:

* It supports loading a record by `remote_id` column as well as `uid`
* It will attempt to load first by `remote_id` then by `uid`
* If neither method is successful it creates a new object and sets the `remoteId` attribute

These behaviors when combined result in a very specific way of operation. It makes relations
between objects completely transparent - even if the input data refers to an object by its
remote ID, the correct object either gets resolved or created on-the-fly to ensure that no
invalid relations will ever be written.

All that is required for a relation to work, is annotating it correctly in the Entity class
and making sure it has the appropriate setter methods. Compound types are automatically
detected and processed, e.g. if the PIM data set contains an array of references to other
objects and the local Entity is correctly annotated, the mapping class knows to resolve each
of those references and yield an ObjectStorage that can be persisted by Extbase.

However, this choice has a few implications:

* One `TypeConverter` must be registered for every possible data type or Entity from PIm
* If no converter can be detected for a given type, the import fails.
* If another `TypeConverter` exists with a higher priority then it gets used. The default
  priority for any `TypeConverter` using the shared base class is a very high `90` for this
  very reason.

> Developer note: `null` is handled by bypassing all of the regular mapping. Deleting a
> property value is possible by passing `null` as the value in the PIM data set.

In order to register a `TypeConverter` you need a single line in `ext_localconf.php`:

```php
<?php
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerTypeConverter(
    \Crossmedia\Products\TypeConverter\ProductTypeConverter::class
);
```


Dynamic domain models
---------------------

The Fourallportal extension features dynamic models on the TYPO3 side, which basically means
that if you construct the model classes in a specific way, there is a command line command
you can execute to generate a base class using properties returned from the remote API.

Opting in to dynamic model properties requires the following steps:

1. The model class file must include a special call to load a dynamically generated base
   class before using it as base class for the model.
2. The TCA file for the model must create the `columns` array using `array_merge` to put
   together the basic properties and the output from a function call which reads the
   remote API's properties for the connector that handles the model class.

Note that the function call which generates the TCA should only be used in the array-returning
TCA definition file for the model's table - since it calls the remote API it would impact
performance negatively if the output was not allowed to be cached, which is the case if it
were used in a so-called `Overrides` TCA file (which directly modifies the TCA array instead
of returning an array).

The rest of the requirements are automatically handled:

1. SQL schema gets generated based on configured Modules and can be updated using the
   normal schema update approaches (install tool or via third party CLI commands).
2. The TCA gets generated automatically based on responses from the remote API.
3. Whenever possible, dynamic model classes are regenerated on-the-fly. If this cannot
   be done an exception is thrown informing the administrator to use the command line.

The following sections describe how to integrate the three main requirements


#### The model class file


#### Preventing use of the automatic model


#### The TCA definitions file


#### The model update command



Expected behavior
-----------------

Assuming that you put all of the above components together correctly, running the import
CLI command should cause the following chain of events to occur:

* Each `Server` is iterated
* Each `Module` from each `Server` is iterated
* The PIM API is queried using the credentials and configuration from `Server` and `Module`
* A list of events are received and spooled
* The events are claimed and processed one by one, performing one of either `update` or
  `delete` actions (note: `create` is compounded into `update` since ad-hoc creation happens)
* If successful, all properties received from PIM are mapped onto the Entity properties
  and saved to the database.
* If any errors should occur, feedback is output identifying the source of the problem.


Developer hints
---------------

The following hints may help developers avoid pitfalls when working with this logic:

1. Reflection is widely used and registration happens in extension config files, and both
   of these asset types are quite eagerly cached by TYPO3. The cache group that contains
   both of these caches is the `system` one which is normally hidden unless you are in
   `Development` context or the system cache flushing was explicitly allowed for your user.
2. Even though `TypeConverts` are used, Extbase's validation logic is not triggered. This
   means you can potentially save values in the DB that cause loading of the Entity to fail
   if for example it is passed as argument to a controller action (unless you disable
   validation for the argument in the controller action itself).
3. TYPO3 contains `TypeConverters` for standard types which may not be possible to override.
   Should you experience problems with this, it is possible to remove an already registered
   `TypeConverter` directly from the `TYPO3_CONF_VARS` array but this is strongly discouraged.
   If a given `TypeConverter` is unable to convert a value, consider wrapping said value
   in a ValueObject you attach to your Entity, then create a `TypeConverter` that converts
   to that type of ValueObject.
4. As far as humanly possible, try to adhere to the best practice described above and make
   your Entity as close to the PIM column structures as you can. Overriding any of the logic
   of the mapping classes or `TypeConverter` base class may cause vital features to stop
   working, e.g. could prevent proper handling of relations. The less you customise, the more
   likely it is that the default rules will handle your object types with no problems.
