# ADB-based Bot Script Host Configuration File Manual
## Demo
```json
{
	"title": "Test script",
	"version": "1.0",
	"constants": 
	{
		"ADB_PATH": "adb",
		"ADB_TCPIP": "192.168.0.100:5555"
	},
	"services":
	{
		"Common":{
			"Random\\Delay":
			{
				"provider": "srand",
				"base": 10,
				"offset": 5
			},
			"Random\\Position":
			{
				"provider": "srand"
			}
		},
		"SGS":{
			"OCR\\Number":{

			}
		},
		"PS":{
			"OCR\\Number":{

			}
		}
	},
	"components":
	{
		"Common": {

		},
		"SGS":
		{
			"Base":
			{
				"package": "com.square_enix.ocsd.magical"
			},
			"AppLaunch":{
				"menu": null,
				"icon":
				{
					"position": "P",
					"X1": 0.055,
					"Y1": 0.422,
					"X2": 0.14,
					"Y2": 0.469
				}
			},
			"GameStart":
			{
				"base": 15
			}
		},
		"PS": 
		{
			"Random\\Srand": 
			{
				"base": 11
			}
		}
	},
	"actions": 
	{
		"init": 
		[
			
			{
				"class": "Common\\ConnectionTest"
			},
			
			{
				"class": "Common\\ResolutionCheck"
			},
			
			{
				"class": "Common\\PressKey",
				"keyevent": 3
			}
		],
		"loop": 
		[
			"ReturnHome",
			"SGS\\AppStop",
			{
				"class": "Common\\FinishAction",
				"count": 1
			}
		],
		"final": 
		[
			
			{
				"class": "@Common\\EchoAction",
				"message": "Entered Finalization Section"
			}
		]
	}
}
```
## The `title` field
`string` Please specify this configuration a name easy to read. It will be read and displayed as soon as this configuration is loaded.
## The `version` field
`string` Like the `title` field, please update it after modification.
## The `constants` field
`object` whose keys are constant name and values are variant data.
Store universal data for all games and components.
Such as ADB connection string, environment variables.
## The `services` field
Constructor params for class located in `AB\Service`
- `object` whose keys are either `Common` or the namespaces of apps as in `AB\Action`.
- `object` whose keys are the relative class name to `AB\Service`, values are `object` as the constructor params to corresponding class. Note that a value named `app` will be filled by current app. 

Manager will create service instance specialized for app if a overriden definition has been made, or return default instance as the `Common` paragraph describes. If none of above definititions are found, empty array will be the constructor param.
## The `components` field
Atomic operations constructor params. It is likely a huge `object`.
Like the `services` field, first level is made of `Common` and app namespaces.
However, the class names under namespace object are dynamic. The manager will search class name under app namespace first, and fail back to the `Common` namespace. You can define `A\Operation` and `B\Operation` respectively without existence of real classes, the manager will return object that constructed with correct param when you request. 
Also, the construction params can not use key `app` which is used by manager.

The recommend naming convention of a component(action) is Verb+Noun.
## The `actions` field
Control the manager how script runs. It is perhaps a big `object`. 
### Control section
The keys of root object are:
- `init` Run once to perform initialization procedures
- `loop` Run repeatly until a action returns breaking signal
- `final` Run after main loop is over

### Actions list array
The values of root object are `array` combined by actions in `AB\Action`.
The possible values of an array element are:
- `string` like `Test`: A class mapped to `AB\Action\Common\Test`
- `string` like `App\Test`: A class mapped to `AB\Action\App\Test` or fail back to `AB\Action\Common\Test`
- `object` which contains a key named `class` whose value is `string`: The `class` key uses the same mapping rules above. Rest keys will act the second param when invoke the `doAction()` of action instances.  

#### Object reuse indicator
The `@` prefix in class name string or `class` value above changes the behaviour of the manager. It always instantites a new object according to the params provided and run it immediately. Normally, the action objects are shared for all actions, later actions can access previous contexts.

## The provider callback definition
Some `IService` classes can take advantage of external providers.
There is an extended syntax to the standard PHP callback notation.
### Static function and method
It is defined as the same as PHP callback type.
- `'function_name'`
- `['class_name', 'static_method_name']` 

### Dynamic object method
It creates an object first and make the callback as `[$object, 'method_name']`.
The JSON should be like below. Note that `class` and `method` keys are reserved and excluded from the configuration array.
The construction expression will be `new $className($manager, $config)`. Note that `class` name can not use relative namespace.
```json
{
	"class": "full_class_name",
	"method": "callback_method_name(if the class implements __invoke(), this can be omitted.)",
	"custom_param_1": "your value",
	"more_param": "more value"
}
```
## Data types
### Time duration
Integer only. The unit is millisecond.
### Geometry things
Please use precentage notation in the configuration file.
#### Point
Example: `{"X": 0.5, "Y": 0.75}` 
#### Rectangle and Line
Example: `{"X1": 0.25, "Y1": 0.25, "X2": 0.5, "Y2": 0.75}`
In the strict mode of rectangle notation, please make point 1 the left-top one and point 2 the right-bottom one.

### Color
First part uses 6-digit HTML scheme without prefix `#`.
Do not use short or literal notations like `999` and `white`. 

Second optional part is decimal acceptable RGB color space distance.
Default value is 8.

Example: `66CCFF:10` means #66CCFF with maximum RGB space distance 10.

### OCR rules
#### Colors - Positions matrix
Test is true only if all colors and points/rectangles are evaluted as true.
```json
{
	"COLOR1": [POINT1, POINT2, RECT],
	"COLOR2": [RECT, POINT]
}
```
#### OCR decision tree 
An object to describe an OCR decision process.
If none of decisions returns any result, the OCR process is failure.
The `J` key contains points to determine. No rectangles are supported here.
The `C` key provides a color overriding previous defined, it affects followed trees until another `C` key is found or the whole tree is ended when it restores default color.
The `T` and `F` keys contain either another tree, or scale `string` as OCR result, or `null` as OCR failure.
```json
{
"J": 
	[{X: 0.14, Y: 0.48}, {X: 0.14, Y: 0.48}],
"T": "1", 
"F": 
	{"J": "2",
	 "T": "3", 
	 "F": null
	}
}
```

#### OCR decision trees array
Just an associated array.

`["rule-name": OCRTreeObject]`

#### OCR area
The OCR area is expressed by rectangle JSON with a special rule.
- Left-align if point 1 is left-top and point 2 is right-bottom
- Right-align if point 1 is right-bottom and point 2 is left-top