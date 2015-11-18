# ADB-based Bot Script Host Configuration File Manual
## Demo
```json
{
	"title": "Script Name",
	"version": "1.0",
	"constants": {
		"ADB": "adb -s 192.168.2.45:5555",
		"HOME_KEYEVENT": 3
	},
	"commons": {
		"Random\\Srand": {
			"base": 10,
			"offset": 5
		}
	},
	"games": {
		"SGS": {
			"OCR\\Number": {},
		},
		"PS": {
			"OCR\\Number": {},
		}
	},
	"actions":{
		"init": [
			{"class": "Common\\AssureADB", "retry": 5},
			{"class": "@Common\\ReturnHome"}
		],
		"loop": [
			{"class": "SGS\\FindPVP", "Position": {"X": 123, "Y": 543}}
		],
		"final": [
		]
	}
}
```
## Special Convention
The `@` prefix for `class` value indicates the manager always instantite a new object according to the params provided. Otherwise, if previous instance has already created, just return it.
