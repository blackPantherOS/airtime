from api_clients import *
import sys

api_clients = api_client.AirTimeApiClient()

dj_type = sys.argv[1]
username = sys.argv[2]
password = sys.argv[3]

source_type = ''
if dj_type == '--master':
    source_type = 'master'
elif dj_type == '--dj':
    source_type = 'dj'

response = api_clients.check_live_stream_auth(username, password, type)

print response['msg']
